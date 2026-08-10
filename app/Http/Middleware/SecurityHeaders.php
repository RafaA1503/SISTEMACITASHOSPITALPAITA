<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        $scriptSrc = "'self'";
        $connectSrc = "'self'";
        if (app()->environment('local')) {
            // El servidor de desarrollo de Vite sirve los assets desde otro origen.
            // Nota: los navegadores no aceptan literales IPv6 entre corchetes
            // (p.ej. http://[::1]:5173) como fuente CSP válida, así que no se incluyen.
            $scriptSrc .= ' http://localhost:5173 http://127.0.0.1:5173';
            $connectSrc .= ' ws://localhost:5173 ws://127.0.0.1:5173 http://localhost:5173 http://127.0.0.1:5173';
        }

        // WebSocket de Reverb (tiempo real): sin esto el navegador bloquea la conexión por CSP.
        $reverb = config('broadcasting.connections.reverb.options');
        if (! empty($reverb['host'])) {
            $reverbWsScheme = ($reverb['useTLS'] ?? true) ? 'wss' : 'ws';
            $reverbHttpScheme = ($reverb['useTLS'] ?? true) ? 'https' : 'http';
            $reverbOrigin = "{$reverb['host']}:{$reverb['port']}";
            $connectSrc .= " {$reverbWsScheme}://{$reverbOrigin} {$reverbHttpScheme}://{$reverbOrigin}";
        }

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "connect-src {$connectSrc}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));

        return $response;
    }
}
