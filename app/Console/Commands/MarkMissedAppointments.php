<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Support\LiveUpdate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkMissedAppointments extends Command
{
    protected $signature = 'appointments:mark-no-shows';

    protected $description = 'Marca automáticamente las citas sin llegada registrada después de la tolerancia.';

    public function handle(): int
    {
        $graceMinutes = max(0, (int) config('hospital.attendance_grace_minutes', 20));
        $cutoff = now()->subMinutes($graceMinutes);
        $updated = 0;

        Appointment::query()
            ->where('status', 'programada')
            ->where('scheduled_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use (&$updated, $cutoff, $graceMinutes) {
                foreach ($appointments as $candidate) {
                    $appointment = DB::transaction(function () use ($candidate, $cutoff, $graceMinutes) {
                        $appointment = Appointment::query()->lockForUpdate()->find($candidate->id);

                        if (! $appointment || $appointment->status !== 'programada' || $appointment->scheduled_at->gt($cutoff)) {
                            return null;
                        }

                        $appointment->update(['status' => 'no_asistio']);
                        DB::table('appointment_status_history')->insert([
                            'appointment_id' => $appointment->id,
                            'changed_by' => null,
                            'from_status' => 'programada',
                            'to_status' => 'no_asistio',
                            'notes' => "Inasistencia automática: no se registró llegada dentro de {$graceMinutes} minutos de tolerancia.",
                            'ip_address' => null,
                            'created_at' => now(),
                        ]);

                        return $appointment;
                    });

                    if (! $appointment) {
                        continue;
                    }

                    $appointment->load(['patient', 'type.service', 'area', 'subarea']);
                    LiveUpdate::broadcast('citas', [[
                        'selector' => '#patientsTableBody',
                        'action' => 'updated',
                        'id' => $appointment->id,
                        'html' => view('portal.partials.patient-row', ['a' => $appointment])->render(),
                    ]]);
                    $updated++;
                }
            });

        $this->info("{$updated} cita(s) marcada(s) como inasistencia.");

        return self::SUCCESS;
    }
}
