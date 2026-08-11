<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\TwilioSms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envía por SMS los recordatorios de citas programadas una hora antes.';

    public function handle(TwilioSms $twilio): int
    {
        if (! $twilio->configured()) {
            $this->warn('Twilio no está configurado. No se enviaron recordatorios.');
            return self::SUCCESS;
        }

        $minutes = max(1, (int) config('twilio.reminder_minutes', 60));
        $from = now()->addMinutes($minutes - 1);
        $until = now()->addMinutes($minutes + 1);
        $sent = 0;

        Appointment::with(['patient', 'type.service'])
            ->where('status', 'programada')
            ->whereBetween('scheduled_at', [$from, $until])
            ->whereNotNull('paciente_id')
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($twilio, &$sent) {
                foreach ($appointments as $appointment) {
                    if (! filled($appointment->patient?->phone) || $this->alreadySent($appointment->id)) {
                        continue;
                    }

                    try {
                        $sid = $twilio->send($appointment->patient->phone, $this->message($appointment));
                        DB::table('appointment_status_history')->insert([
                            'appointment_id' => $appointment->id,
                            'changed_by' => null,
                            'from_status' => $appointment->status,
                            'to_status' => $appointment->status,
                            'notes' => 'SMS_RECORDATORIO_TWILIO:'.$sid,
                            'ip_address' => null,
                            'created_at' => now(),
                        ]);
                        $sent++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error("No se pudo enviar el recordatorio de la cita {$appointment->code}.");
                    }
                }
            });

        $this->info("{$sent} recordatorio(s) enviado(s).");
        return self::SUCCESS;
    }

    private function alreadySent(int $appointmentId): bool
    {
        return DB::table('appointment_status_history')
            ->where('appointment_id', $appointmentId)
            ->where('notes', 'like', 'SMS_RECORDATORIO_TWILIO:%')
            ->exists();
    }

    private function message(Appointment $appointment): string
    {
        $date = $appointment->scheduled_at->isToday() ? 'hoy' : 'el '.$appointment->scheduled_at->format('d/m/Y');
        $place = $appointment->room ? ' en '.$appointment->room : '';

        return "Hospital Nuestra Señora de las Mercedes: {$appointment->patient->full_name}, recuerde su cita {$date} a las {$appointment->scheduled_at->format('H:i')} en {$appointment->type->service->name}{$place}. Si no podrá asistir, comuníquese con el hospital.";
    }
}
