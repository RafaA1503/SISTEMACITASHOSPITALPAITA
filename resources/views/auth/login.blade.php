<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ingresar | Hospital Nuestra Señora de las Mercedes</title><meta name="app-version" content="{{ \App\Support\AppVersion::hash() }}"><link rel="manifest" href="{{ asset('manifest.json') }}"><meta name="theme-color" content="#0c3188"><link rel="icon" href="{{ asset('icons/icon-192.png') }}"><link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="auth-body"><button type="button" id="themeToggle" class="theme-toggle" aria-label="Cambiar tema"></button><main class="login-shell">
<section class="login-brand">
<div class="login-carousel" data-login-carousel aria-label="Imágenes del Hospital Nuestra Señora de las Mercedes">
<figure class="login-carousel-slide is-active"><img src="{{ asset('images/hospital-fachada-1.png') }}" alt="Fachada principal del Hospital Nuestra Señora de las Mercedes" fetchpriority="high"></figure>
<figure class="login-carousel-slide"><img src="{{ asset('images/hospital-fachada-2.png') }}" alt="Ingreso del Hospital Nuestra Señora de las Mercedes"></figure>
<figure class="login-carousel-slide"><img src="{{ asset('images/hospital-fachada-3.png') }}" alt="Edificio del Hospital Nuestra Señora de las Mercedes"></figure>
<div class="login-carousel-controls"><button type="button" data-carousel-prev aria-label="Imagen anterior">‹</button><div role="tablist" aria-label="Seleccionar imagen"><button type="button" class="is-active" data-carousel-dot aria-label="Imagen 1" aria-selected="true"></button><button type="button" data-carousel-dot aria-label="Imagen 2" aria-selected="false"></button><button type="button" data-carousel-dot aria-label="Imagen 3" aria-selected="false"></button></div><button type="button" data-carousel-next aria-label="Imagen siguiente">›</button></div>
</div>
<div class="login-brand-content"><img src="{{ asset('logo-hospital-la-merced.png') }}" alt="Logo"><div><p>SISTEMA HOSPITALARIO</p><h1>Hospital Nuestra Señora de las Mercedes</h1><span>Acceso seguro para el personal autorizado.</span></div></div><small>Red de Salud · Paita · Piura</small></section>
<section class="login-card"><div><p class="eyebrow">BIENVENIDO</p><h2>Iniciar sesión</h2><span>Ingresa con tu cuenta institucional.</span></div>
@if(session('success'))<div class="success-banner">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="auth-error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}">@csrf
<label>Correo institucional o usuario<input name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" maxlength="150" placeholder="correo@lamerced.gob.pe o usuario"></label>
<label>Contraseña<div class="password-field"><input id="loginPassword" name="password" type="password" required autocomplete="current-password" placeholder="••••••••"><button id="togglePassword" type="button">Mostrar</button></div></label>
<label class="login-letter-captcha"><span>Verificación de seguridad</span><div><b id="captchaCode" aria-label="Código de seguridad">{{ $captchaCode }}</b><button type="button" id="refreshCaptcha" data-url="{{ route('captcha.refresh') }}" aria-label="Generar otro código" title="Generar otro código">↻</button><input name="captcha" required minlength="6" maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Escribe el código"></div><small>Escribe las 6 letras o números que aparecen en la imagen.</small></label>
<label class="remember"><input type="checkbox" name="remember"> Mantener sesión iniciada</label><button class="login-primary">Ingresar al sistema</button></form>
<a class="forgot-password-link" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
<div class="auth-divider"><span>o ingresa de forma segura</span></div><button class="passkey-login" id="passkeyLogin"><span>◎</span><div><strong>Usar huella o Passkey</strong><small>Huella, Face ID o Windows Hello</small></div></button><p class="auth-note">La información biométrica permanece protegida en tu dispositivo.</p>
</section></main></body></html>
