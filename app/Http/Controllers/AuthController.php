<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Service;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginView(Request $request)
    {
        if (! $request->session()->has('login_captcha_hash')) $this->generateCaptcha($request);
        return view('auth.login', ['captchaCode' => $request->session()->get('login_captcha_code')]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:150',
            'password' => 'required',
            'captcha' => 'required|string|size:6',
        ], ['captcha.required' => 'Escribe el código de seguridad.', 'captcha.size' => 'El código de seguridad debe tener 6 caracteres.']);
        $captchaHash = (string) $request->session()->pull('login_captcha_hash', '');
        $request->session()->forget('login_captcha_code');
        if ($captchaHash === '' || ! hash_equals($captchaHash, hash('sha256', strtoupper(trim($data['captcha']))))) {
            $this->generateCaptcha($request);
            return back()->withErrors(['captcha' => 'El código de seguridad no es correcto.'])->onlyInput('login');
        }
        $login = mb_strtolower(trim($data['login']));

        $throttleKey = $login.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors(['login' => "Demasiados intentos. Intenta nuevamente en {$seconds} segundos."])->onlyInput('login');
        }

        // Permite correo o usuario, siempre limitado al sistema actual.
        $user = User::where('idSistema', User::sistemaId())
            ->where(function ($query) use ($login) {
                $query->whereRaw('LOWER(correo) = ?', [$login])
                    ->orWhereRaw('LOWER(usuario) = ?', [$login]);
            })->first();
        if (! $user || ! Hash::check($data['password'], $user->getAuthPassword())) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['login' => 'Correo, usuario o contraseña incorrectos.'])->onlyInput('login');
        }
        Auth::login($user, $request->boolean('remember'));
        RateLimiter::clear($throttleKey);
        if (! $request->user()->active) {
            Auth::logout();
            return back()->withErrors(['login' => 'La cuenta está desactivada.']);
        }
        $request->session()->regenerate();
        return redirect()->route('portal', $request->user()->defaultModule());
    }

    public function refreshCaptcha(Request $request)
    {
        $this->generateCaptcha($request);
        return response()->json(['code' => $request->session()->get('login_captcha_code')]);
    }

    private function generateCaptcha(Request $request): void
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = collect(range(1, 6))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        $request->session()->put(['login_captcha_code' => $code, 'login_captcha_hash' => hash('sha256', $code)]);
    }

    public function logout(Request $request) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
    public function settings(Request $request)
    {
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()->map(function ($session) use ($request) {
            $session->last_seen = \Carbon\Carbon::createFromTimestamp($session->last_activity)->timezone(config('app.timezone'))->format('d/m/Y H:i');
            $session->current = hash_equals($request->session()->getId(), $session->id);
            $session->device = $this->sessionDevice((string) $session->user_agent);
            return $session;
        });

        return view('auth.settings', ['user' => $request->user(), 'passkeys' => $request->user()->passkeys, 'sessions' => $sessions]);
    }

    public function sessionStatus(Request $request)
    {
        $user = $request->user();
        $sessionKey = 'force_password_logout_session_'.$request->session()->getId();
        if (! Cache::has($sessionKey)) {
            return response()->json(['active' => true]);
        }

        Cache::forget($sessionKey);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Un administrador cambió tu contraseña. Inicia sesión nuevamente.'], 409);
    }
    public function photo(Request $request)
    {
        abort_unless($request->user()->photo_path && Storage::disk('public')->exists($request->user()->photo_path), 404);
        return Storage::disk('public')->response($request->user()->photo_path);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240']);
        $user = $request->user();

        if ($user->idPersona) {
            $user->persona()->update(['nombres' => $data['name']]);
        }

        $photoPath = $user->photo_path;
        if ($request->hasFile('photo')) {
            if ($photoPath) Storage::disk('public')->delete($photoPath);
            $photoPath = $request->file('photo')->store('profiles', 'public');
        }
        UserProfile::updateOrCreate(['user_id' => $user->id], ['photo_path' => $photoPath]);

        return back()->with('success', 'Perfil y fotografía actualizados.');
    }

    public function password(Request $request)
    {
        $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(8)]]);
        $user = $request->user();
        $user->update(['password' => Hash::make($request->password)]);

        // La contraseña vieja ya no debería servir en ningún otro dispositivo:
        // se marcan sus otras sesiones para que el polling de sessionStatus()
        // las cierre solas en cuanto el navegador vuelva a consultar.
        $this->forceLogoutOtherSessions($user->id, $request->session()->getId());

        return back()->with('success', 'Contraseña actualizada. Tus otras sesiones se cerrarán automáticamente.');
    }

    private function forceLogoutOtherSessions(int $userId, string $currentSessionId): void
    {
        DB::table('sessions')->where('user_id', $userId)->where('id', '!=', $currentSessionId)
            ->pluck('id')->each(fn ($sessionId) => Cache::put('force_password_logout_session_'.$sessionId, true, now()->addMinutes(15)));
    }

    public function users(Request $request)
    {
        abort_unless($request->user()->role === 'administrador', 403);

        return view('auth.users', [
            'users' => User::where('idSistema', User::sistemaId())->with(['persona', 'rol', 'profile'])->get()->sortBy('name')->values(),
            'services' => Service::where('active', 1)->get(),
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless($request->user()->role === 'administrador', 403);
        abort_unless($user->idSistema === User::sistemaId(), 404);

        $data = $request->validate([
            'role' => 'required|in:administrador,portero,admision,profesional,laboratorio,imagenes',
            'service_id' => 'nullable|exists:services,id',
            'active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
        ]);

        $before = ['role' => $user->role, 'service_id' => $user->service_id, 'active' => $user->active, 'permissions' => $user->permissions];

        $rolId = Rol::where('nombreRol', $data['role'])->where('idSistema', User::sistemaId())->value('idRol');
        $user->update(['idRol' => $rolId, 'Estado' => $request->boolean('active')]);

        if (! empty($data['service_id']) && $user->idPersona) {
            $legacyServiceId = Service::find($data['service_id'])?->legacy_id;
            if ($legacyServiceId) {
                \App\Models\Trabajador::updateOrCreate(
                    ['idPersona' => $user->idPersona],
                    ['idServicio' => $legacyServiceId, 'estado' => 1]
                );
            }
        }

        UserProfile::updateOrCreate(['user_id' => $user->id], ['permissions' => $data['permissions'] ?? []]);

        AuditLogger::log($request, 'user.updated', 'User', $user->id, ['before' => $before, 'after' => $data]);

        $html = view('auth.partials.user-card', [
            'item' => $user->fresh(['persona', 'rol', 'profile']),
            'services' => Service::where('active', 1)->get(),
        ])->render();
        if ($response = LiveUpdate::respond($request, 'admin.users', '#usersList', 'updated', $user->id, $html)) {
            return $response;
        }

        return back()->with('success', 'Rol y funciones actualizados.');
    }

    private function sessionDevice(string $userAgent): string
    {
        $browser = str_contains($userAgent, 'Edg/') ? 'Microsoft Edge' : (str_contains($userAgent, 'Firefox/') ? 'Firefox' : (str_contains($userAgent, 'Chrome/') ? 'Google Chrome' : 'Navegador'));
        $platform = str_contains($userAgent, 'Windows') ? 'Windows' : (str_contains($userAgent, 'Android') ? 'Android' : (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') ? 'iOS' : 'Dispositivo'));
        return $browser.' · '.$platform;
    }
}
