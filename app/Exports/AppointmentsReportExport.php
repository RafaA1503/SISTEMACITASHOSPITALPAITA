<?php

namespace App\Exports;

use App\Models\Appointment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AppointmentsReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Collection $appointments)
    {
    }

    public function collection(): Collection
    {
        return $this->appointments;
    }

    public function headings(): array
    {
        return ['Fecha', 'Hora programada', 'Paciente', 'DNI', 'Servicio', 'Atención', 'Profesional', 'Hora de llegada', 'Puntualidad', 'Estado'];
    }

    public function map($appointment): array
    {
        /** @var Appointment $appointment */
        return [
            $appointment->scheduled_at->format('d/m/Y'),
            $appointment->scheduled_at->format('H:i'),
            $appointment->patient->full_name,
            $appointment->patient->dni,
            $appointment->type->service->name,
            $appointment->type->name,
            $appointment->professional?->name ?? 'Por asignar',
            $appointment->confirmed_at?->format('H:i') ?? '',
            $appointment->punctuality === 'puntual' ? 'Puntual' : ($appointment->punctuality === 'tardanza' ? "Tardanza {$appointment->late_minutes} min" : ''),
            ucfirst(str_replace('_', ' ', $appointment->status)),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
