<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginView() { return view('auth.login'); }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required']);
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Correo o contraseña incorrectos.'])->onlyInput('email');
        }
        if (! $request->user()->active) {
            Auth::logout();
            return back()->withErrors(['email' => 'La cuenta está desactivada.']);
        }
        $request->session()->regenerate();

        $module=match($request->user()->role){'portero'=>'portero','admision'=>'citas','administrador'=>'administrador',default=>'servicio'};
        return redirect()->route('portal', $module);
    }

    public function logout(Request $request) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
    public function settings(Request $request) { return view('auth.settings', ['user' => $request->user(), 'passkeys' => $request->user()->passkeys]); }
    public function photo(Request $request)
    {
        abort_unless($request->user()->photo_path && Storage::disk('public')->exists($request->user()->photo_path), 404);
        return Storage::disk('public')->response($request->user()->photo_path);
    }
    public function updateProfile(Request $request) { $data=$request->validate(['name'=>'required|string|max:120','photo'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:10240']); $user=$request->user(); $user->name=$data['name']; if($request->hasFile('photo')) { if($user->photo_path) Storage::disk('public')->delete($user->photo_path); $user->photo_path=$request->file('photo')->store('profiles','public'); } $user->save(); return back()->with('success','Perfil y fotografía actualizados.'); }
    public function password(Request $request) { $request->validate(['current_password'=>'required|current_password','password'=>['required','confirmed',Password::min(8)]]); $request->user()->update(['password'=>Hash::make($request->password)]); return back()->with('success','Contraseña actualizada.'); }
    public function users(Request $request) { abort_unless($request->user()->role==='administrador',403); return view('auth.users',['users'=>User::with('service')->orderBy('name')->get(),'services'=>Service::where('active',1)->get()]); }
    public function updateUser(Request $request, User $user) { abort_unless($request->user()->role==='administrador',403); $data=$request->validate(['role'=>'required|in:administrador,portero,admision,profesional,laboratorio,imagenes','service_id'=>'nullable|exists:services,id','active'=>'nullable|boolean','permissions'=>'nullable|array']); $user->update(['role'=>$data['role'],'service_id'=>$data['service_id']??null,'active'=>$request->boolean('active'),'permissions'=>$data['permissions']??[]]); return back()->with('success','Rol y funciones actualizados.'); }
}
