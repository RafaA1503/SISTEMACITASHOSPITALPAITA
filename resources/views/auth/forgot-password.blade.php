<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recuperar contraseña | Hospital Nuestra Señora de las Mercedes</title><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="auth-body"><main class="login-shell">
<section class="login-brand"><img src="{{ asset('logo-hospital-la-merced.png') }}" alt="Logo"><div><p>SISTEMA HOSPITALARIO</p><h1>Hospital Nuestra Señora de las Mercedes</h1><span>Acceso seguro para el personal autorizado.</span></div><small>Red de Salud · Paita · Piura</small></section>
<section class="login-card"><div><p class="eyebrow">RECUPERAR ACCESO</p><h2>¿Olvidaste tu contraseña?</h2><span>Ingresa tu correo institucional y te enviaremos un enlace para restablecerla.</span></div>
@if(session('success'))<div class="success-banner">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="auth-error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.email') }}">@csrf
<label>Correo institucional<input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="usuario@lamerced.gob.pe"></label>
<button class="login-primary">Enviar enlace de recuperación</button></form>
<a class="forgot-password-link" href="{{ route('login') }}">← Volver a iniciar sesión</a>
</section></main></body></html>
