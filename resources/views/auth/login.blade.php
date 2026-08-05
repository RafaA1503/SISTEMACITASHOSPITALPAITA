<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ingresar | Hospital La Merced Paita</title><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="auth-body"><main class="login-shell">
<section class="login-brand"><img src="{{ asset('logo-hospital-la-merced.png') }}" alt="Logo"><div><p>SISTEMA HOSPITALARIO</p><h1>Hospital La Merced Paita</h1><span>Acceso seguro para el personal autorizado.</span></div><small>Red de Salud · Paita · Piura</small></section>
<section class="login-card"><div><p class="eyebrow">BIENVENIDO</p><h2>Iniciar sesión</h2><span>Ingresa con tu cuenta institucional.</span></div>
@if($errors->any())<div class="auth-error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}">@csrf
<label>Correo institucional<input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="usuario@lamerced.gob.pe"></label>
<label>Contraseña<div class="password-field"><input id="loginPassword" name="password" type="password" required autocomplete="current-password" placeholder="••••••••"><button id="togglePassword" type="button">Mostrar</button></div></label>
<label class="remember"><input type="checkbox" name="remember"> Mantener sesión iniciada</label><button class="login-primary">Ingresar al sistema</button></form>
<div class="auth-divider"><span>o ingresa de forma segura</span></div><button class="passkey-login" id="passkeyLogin"><span>◎</span><div><strong>Usar huella o Passkey</strong><small>Huella, Face ID o Windows Hello</small></div></button><p class="auth-note">La información biométrica permanece protegida en tu dispositivo.</p>
</section></main></body></html>
