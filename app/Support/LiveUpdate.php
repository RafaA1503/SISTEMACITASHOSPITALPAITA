<?php

namespace App\Support;

use App\Events\RowUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveUpdate
{
    /**
     * Caso simple: un solo contenedor afectado.
     * Difunde el cambio a todos los demás usuarios conectados a $channel, y si
     * la petición que originó el cambio pidió JSON (fetch/AJAX), devuelve la
     * respuesta que el propio navegador debe aplicar de inmediato — así el
     * autor del cambio no depende del round-trip del WebSocket para verlo.
     * Si la petición no pidió JSON (formulario clásico sin JS), devuelve null
     * para que el controlador siga con su `back()->with(...)` de siempre.
     */
    public static function respond(Request $request, string $channel, string $selector, string $action, int|string $id, ?string $html): ?JsonResponse
    {
        return self::respondMulti($request, $channel, [
            ['selector' => $selector, 'action' => $action, 'id' => $id, 'html' => $html],
        ]);
    }

    /**
     * Caso general: varios contenedores afectados por el mismo cambio, cada
     * uno con su propia acción (ej. una cita se actualiza en Portería pero
     * recién aparece — "created" — en Atención profesional al confirmarse).
     *
     * @param array<int,array{selector:string,action:string,id:int|string,html:?string}> $targets
     */
    public static function respondMulti(Request $request, string $channel, array $targets): ?JsonResponse
    {
        // El guardado ya se hizo antes de llegar aquí: si el servidor de
        // WebSockets (Reverb) no está disponible, la difusión en vivo a otras
        // pestañas es solo un extra — no debe tumbar la respuesta ni hacer
        // perder los cambios que sí se guardaron en la base de datos.
        self::broadcast($channel, $targets, true);

        return $request->wantsJson()
            ? response()->json(['targets' => $targets])
            : null;
    }

    /** Difunde cambios iniciados por tareas automáticas, sin una petición HTTP. */
    public static function broadcast(string $channel, array $targets, bool $exceptCurrentConnection = false): void
    {
        try {
            $event = broadcast(new RowUpdated($channel, $targets));
            if ($exceptCurrentConnection) {
                $event->toOthers();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
