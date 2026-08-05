<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Ngrok y otros proxies terminan HTTPS antes de reenviar la petición.
        // Confiar en sus cabeceras evita generar recursos HTTP bloqueados en móviles.
        $middleware->trustProxies(at: '*');
        // El tema lo escribe JavaScript y debe poder leerse antes de renderizar el HTML.
        $middleware->encryptCookies(except: ['hospital_theme']);
        $middleware->appendToGroup('web', \App\Http\Middleware\ApplySavedTheme::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
