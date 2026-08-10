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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginView() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required']);
        $correo = strtolower(trim($data['email']));

        $throttleKey = $correo.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors(['email' => "Demasiados intentos. Intenta nuevamente en {$seconds} segundos."])->onlyInput('email');
        }

        // Escopeado a nuestro sistema: la misma persona puede tener cuentas en
        // otros sistemas de SIGESA con el mismo correo.
        $credentials = ['correo' => $correo, 'password' => $data['password'], 'idSistema' => User::sistemaId()];
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['email' => 'Correo o contraseña incorrectos.'])->onlyInput('email');
        }
        RateLimiter::clear($throttleKey);
        if (! $request->user()->active) {
            Auth::logout();
            return back()->withErrors(['email' => 'La cuenta está desactivada.']);
        }
        $request->session()->regenerate();

        return redirect()->route('portal', $request->user()->defaultModule());
    }

    public function logout(Request $request) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
    public function settings(Request $request) { return view('auth.settings', ['user' => $request->user(), 'passkeys' => $request->user()->passkeys]); }
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
        $request->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Contraseña actualizada.');
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
}
