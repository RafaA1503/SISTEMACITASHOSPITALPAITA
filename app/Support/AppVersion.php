<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

// Detecta si el código del servidor cambió (PHP, Blade, JS, CSS fuente) sin
// depender de que alguien recuerde "subir la versión" a mano: toma la fecha
// de modificación más reciente entre las carpetas fuente. El navegador
// compara este valor cada cierto tiempo contra el que tenía al cargar la
// página, y si cambió, avisa que hay una actualización disponible.
class AppVersion
{
    private const WATCHED_PATHS = [
        'app',
        'resources/views',
        'resources/js',
        'resources/css',
        'routes',
    ];

    public static function hash(): string
    {
        return Cache::remember('app_version_hash', 10, function () {
            $latest = 0;
            foreach (self::WATCHED_PATHS as $relative) {
                $path = base_path($relative);
                if (! is_dir($path)) {
                    continue;
                }
                foreach (File::allFiles($path) as $file) {
                    $latest = max($latest, $file->getMTime());
                }
            }

            return (string) $latest;
        });
    }
}
