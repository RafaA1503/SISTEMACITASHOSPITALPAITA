<?php

namespace App\Http\Controllers;

use App\Exports\AppointmentsReportExport;
use App\Models\Appointment;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public const PUNCTUALITY_GRACE_MINUTES = 15;

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        [$appointments, $from, $to, $serviceId] = $this->filteredAppointments($request);

        return view('auth.reports', [
            'appointments' => $appointments,
            'services' => Service::where('active', true)->orderBy('name')->get(),
            'filters' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'service_id' => $serviceId],
            'metrics' => $this->metrics($appointments),
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

        $pdf = Pdf::loadView('auth.reports-pdf', [
            'appointments' => $appointments,
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

        $appointments = Appointment::with(['patient', 'type.service', 'professional'])
            ->whereBetween('scheduled_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($serviceId, fn ($query) => $query->whereHas('type', fn ($t) => $t->where('service_id', $serviceId)))
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(function (Appointment $appointment) {
                $lateMinutes = null;
                $punctuality = null;
                if ($appointment->confirmed_at) {
                    $lateMinutes = max(0, intdiv($appointment->confirmed_at->timestamp - $appointment->scheduled_at->timestamp, 60));
                    $punctuality = $lateMinutes > self::PUNCTUALITY_GRACE_MINUTES ? 'tardanza' : 'puntual';
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
}
