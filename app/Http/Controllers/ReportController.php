<?php

namespace App\Http\Controllers;

use App\Exports\AppointmentsReportExport;
use App\Models\Appointment;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        [$appointments, $from, $to, $serviceId] = $this->filteredAppointments($request);
        $hospitalPatient = trim($request->string('hospital_patient')->toString());
        $accessEntries = $this->accessEntries($from, $to);
        $hospitalizations = $this->hospitalizations($from, $to);
        $hospitalVisits = $this->hospitalVisits($from, $to, $hospitalPatient);

        return view('auth.reports', [
            'appointments' => $appointments,
            'accessEntries' => $accessEntries,
            'hospitalizations' => $hospitalizations,
            'hospitalVisits' => $hospitalVisits,
            'hospitalVisitPatients' => $this->hospitalVisitPatients(),
            'services' => Service::where('active', true)->orderBy('name')->get(),
            'filters' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'service_id' => $serviceId, 'hospital_patient' => $hospitalPatient],
            'metrics' => $this->metrics($appointments),
            'charts' => $this->charts($appointments, $accessEntries, $hospitalizations, $hospitalVisits, $from, $to),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeAdmin($request);
        [$appointments, $from, $to] = $this->filteredAppointments($request);

        return Excel::download(new AppointmentsReportExport($appointments), "reporte-citas-{$from->format('Ymd')}-{$to->format('Ymd')}.xlsx");
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAdmin($request);
        [$appointments, $from, $to] = $this->filteredAppointments($request);
        $hospitalPatient = trim($request->string('hospital_patient')->toString());
        $accessEntries = $this->accessEntries($from, $to);
        $hospitalizations = $this->hospitalizations($from, $to);
        $hospitalVisits = $this->hospitalVisits($from, $to, $hospitalPatient);

        $pdf = Pdf::loadView('auth.reports-pdf', [
            'appointments' => $appointments,
            'accessEntries' => $accessEntries,
            'hospitalizations' => $hospitalizations,
            'hospitalVisits' => $hospitalVisits,
            'hospitalPatient' => $hospitalPatient,
            'metrics' => $this->metrics($appointments),
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("reporte-citas-{$from->format('Ymd')}-{$to->format('Ymd')}.pdf");
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'administrador', 403);
    }

    private function filteredAppointments(Request $request): array
    {
        $from = $request->date('from') ?: today();
        $to = $request->date('to') ?: today();
        $serviceId = $request->integer('service_id') ?: null;

        $appointments = Appointment::with(['patient', 'type.service', 'trabajador.persona'])
            ->whereBetween('scheduled_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($serviceId, fn ($query) => $query->whereHas('type', fn ($t) => $t->where('service_id', $serviceId)))
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(function (Appointment $appointment) {
                $lateMinutes = null;
                $punctuality = null;
                if ($appointment->confirmed_at) {
                    $lateMinutes = max(0, intdiv($appointment->confirmed_at->timestamp - $appointment->scheduled_at->timestamp, 60));
                    $punctuality = $lateMinutes > (int) config('hospital.attendance_grace_minutes', 20) ? 'tardanza' : 'puntual';
                }
                $appointment->late_minutes = $lateMinutes;
                $appointment->punctuality = $punctuality;

                return $appointment;
            });

        return [$appointments, $from, $to, $serviceId];
    }

    private function metrics(Collection $appointments): array
    {
        $total = $appointments->count();
        $attended = $appointments->where('status', 'atendida')->count();
        $noShow = $appointments->where('status', 'no_asistio')->count();
        $confirmedCount = $appointments->whereNotNull('confirmed_at')->count();
        $onTime = $appointments->where('punctuality', 'puntual')->count();
        $late = $appointments->where('punctuality', 'tardanza')->count();

        return [
            'total' => $total,
            'attended' => $attended,
            'no_show' => $noShow,
            'confirmed' => $confirmedCount,
            'on_time' => $onTime,
            'late' => $late,
            'punctuality_rate' => $confirmedCount ? round($onTime / $confirmedCount * 100) : 0,
            'attendance_rate' => $total ? round($attended / $total * 100) : 0,
            'no_show_rate' => $total ? round($noShow / $total * 100) : 0,
        ];
    }

    /** Movimientos efectivamente registrados por Portería, incluso si no están ligados a una cita. */
    private function accessEntries($from, $to): Collection
    {
        return DB::table('access_logs as access')
            ->leftJoin('pacientes as patient', 'patient.IdPaciente', '=', 'access.paciente_id')
            ->whereBetween('access.registered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('access.registered_at')
            ->select([
                'access.registered_at', 'access.movement', 'access.access_point', 'access.reason', 'access.person_type',
                'patient.NroDocumento as dni',
                DB::raw("TRIM(CONCAT_WS(' ', patient.PrimerNombre, patient.SegundoNombre, patient.TercerNombre, patient.ApellidoPaterno, patient.ApellidoMaterno)) as patient_name"),
            ])
            ->get();
    }

    /** Hospitalizaciones vigentes y el acompañante registrado en la estructura SIGESA existente. */
    private function hospitalizations($from, $to): Collection
    {
        return DB::table('atenciones as attention')
            ->join('pacientes as patient', 'patient.IdPaciente', '=', 'attention.IdPaciente')
            ->leftJoin('atencionesdatosadicionales as extra', 'extra.idAtencion', '=', 'attention.IdAtencion')
            ->whereDate('attention.FechaIngreso', '<=', $to->format('Y-m-d'))
            ->where(function ($query) use ($from) {
                $query->whereNull('attention.FechaEgreso')
                    ->orWhere('attention.FechaEgreso', '0000-00-00')
                    ->orWhereDate('attention.FechaEgreso', '>=', $from->format('Y-m-d'));
            })
            ->orderByDesc('attention.FechaIngreso')
            ->select([
                'attention.IdAtencion', 'attention.FechaIngreso', 'attention.FechaEgreso',
                'patient.NroDocumento as dni',
                'extra.NombreAcompaniante as visitor_name', 'extra.Telefono_Acompaniante as visitor_phone',
                'extra.Observacion as visitor_notes',
                DB::raw("TRIM(CONCAT_WS(' ', patient.PrimerNombre, patient.SegundoNombre, patient.TercerNombre, patient.ApellidoPaterno, patient.ApellidoMaterno)) as patient_name"),
            ])
            ->get();
    }

    /** Visitas registradas por Porter\u00eda. Se usa la tabla existente access_logs y el JSON de notas. */
    private function hospitalVisits($from, $to, ?string $hospitalPatient = null): Collection
    {
        $visits = DB::table('access_logs')
            ->where('person_type', 'visitante')
            ->whereBetween('registered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('registered_at')
            ->get()
            ->map(function ($log) {
                $notes = json_decode((string) $log->notes, true) ?: [];

                return (object) [
                    'id' => $log->id,
                    'registered_at' => $log->registered_at,
                    'patient_label' => $notes['patient_label'] ?? $log->reason ?? 'Paciente hospitalizado',
                    'visitor_name' => $notes['visitor_name'] ?? 'Visitante sin nombre',
                    'visitor_dni' => $notes['visitor_dni'] ?? null,
                    'relationship' => $notes['relationship'] ?? 'Sin parentesco',
                    'checked_out_at' => $notes['checked_out_at'] ?? null,
                ];
            });

        return filled($hospitalPatient)
            ? $visits->filter(fn ($visit) => $visit->patient_label === $hospitalPatient)->values()
            : $visits;
    }

    /** Pacientes que tienen al menos una visita hospitalaria registrada. */
    private function hospitalVisitPatients(): Collection
    {
        return DB::table('access_logs')
            ->where('person_type', 'visitante')
            ->orderByDesc('registered_at')
            ->get(['notes', 'reason'])
            ->map(function ($log) {
                $notes = json_decode((string) $log->notes, true) ?: [];

                return $notes['patient_label'] ?? $log->reason;
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function charts(Collection $appointments, Collection $accessEntries, Collection $hospitalizations, Collection $hospitalVisits, Carbon $from, Carbon $to): array
    {
        $days = collect();
        for ($date = $from->copy(); $date->lte($to) && $days->count() < 31; $date->addDay()) {
            $key = $date->format('Y-m-d');
            $entries = $accessEntries->filter(fn ($entry) => Carbon::parse($entry->registered_at)->format('Y-m-d') === $key);
            $days->push([
                'label' => $date->format('d/m'),
                'ingresos' => $entries->where('movement', 'ingreso')->count(),
                'salidas' => $entries->where('movement', 'salida')->count(),
            ]);
        }

        return [
            'statuses' => [
                'programadas' => $appointments->where('status', 'programada')->count(),
                'confirmadas' => $appointments->where('status', 'confirmada')->count(),
                'atendidas' => $appointments->where('status', 'atendida')->count(),
                'faltas' => $appointments->where('status', 'no_asistio')->count(),
            ],
            'days' => $days,
            'hospitalized' => $hospitalizations->filter(fn ($item) => empty($item->FechaEgreso) || $item->FechaEgreso === '0000-00-00')->count(),
            'visitors' => $hospitalVisits->count() + $hospitalizations->filter(fn ($item) => filled($item->visitor_name))->count(),
            'accesses' => $accessEntries->count(),
        ];
    }
}
