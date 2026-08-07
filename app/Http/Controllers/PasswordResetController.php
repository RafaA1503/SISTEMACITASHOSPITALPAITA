<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function forgotView()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        // `email` no es una columna real de `users` (es `correo`): el broker
        // resuelve al usuario por columna real, aunque el resto del flujo
        // (token, notificación) sigue usando el accessor `email` del modelo.
        Password::sendResetLink(['correo' => strtolower(trim($data['email']))]);

        // Mensaje genérico siempre: no revela si el correo existe en el sistema.
        return back()->with('success', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.');
    }

    public function resetView(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $credentials = [
            'token' => $data['token'],
            'correo' => strtolower(trim($data['email'])),
            'password' => $data['password'],
        ];
        $status = Password::reset($credentials, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.')
            : back()->withErrors(['email' => 'El enlace no es válido o expiró. Solicita uno nuevo.']);
    }
}
