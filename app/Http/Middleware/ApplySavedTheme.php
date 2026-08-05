<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySavedTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $theme = $request->cookie('hospital_theme');

        if (in_array($theme, ['light', 'dark'], true)
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && method_exists($response, 'getContent')) {
            $content = $response->getContent();
            if (is_string($content)) {
                $response->setContent(preg_replace('/<html(?![^>]*data-theme)/i', '<html data-theme="'.$theme.'"', $content, 1));
            }
        }

        return $response;
    }
}
