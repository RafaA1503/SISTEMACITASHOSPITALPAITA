<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

// Evento genérico de actualización en vivo: una o más filas/tarjetas cambiaron
// en un canal (pantalla) determinado. El HTML ya viene renderizado desde el
// mismo partial Blade que usa la carga inicial de la página, para no duplicar
// lógica de formato entre el servidor y el navegador.
//
// $targets: lista de ['selector' => '#contenedorCss', 'action' => 'created'|'updated'|'deleted', 'id' => ..., 'html' => '<tr>...'|null]
// Una misma acción puede afectar varios contenedores con acciones distintas
// entre sí (ej. confirmar una cita la actualiza en Portería pero la crea por
// primera vez en la vista de Atención profesional).
class RowUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $channel,
        public array $targets,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'row.updated';
    }

    public function broadcastWith(): array
    {
        return ['targets' => $this->targets];
    }
}
