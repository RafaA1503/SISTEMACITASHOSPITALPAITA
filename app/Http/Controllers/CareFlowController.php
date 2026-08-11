<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CareFlowController extends Controller
{
    private const FLOWS = [
        'externa' => ['Consulta externa', 'Atencion ambulatoria programada: ingreso, orientacion y salida el mismo dia.'],
        'interna' => ['Consulta interna e interconsulta', 'Pacientes hospitalizados que requieren atencion de otro servicio o especialidad.'],
        'hospitalizacion' => ['Hospitalizacion', 'Pacientes con internamiento activo, por servicio de ingreso y cama registrada.'],
    ];

    public function index(Request $request, string $flow = 'externa'): View
    {
        abort_unless($request->user()->canAccessModule('portero') || $request->user()->canAccessModule('administrador'), 403);
        abort_unless(array_key_exists($flow, self::FLOWS), 404);

        $date = $request->date('fecha') ?: today();
        $serviceId = $request->integer('servicio') ?: null;
        $services = Service::where('active', true)->orderBy('name')->get();
        $records = $this->records($flow, $date, $serviceId);

        return view('care-flow', [
            'flow' => $flow,
            'flowTitle' => self::FLOWS[$flow][0],
            'flowDescription' => self::FLOWS[$flow][1],
            'date' => $date,
            'serviceId' => $serviceId,
            'services' => $services,
            'records' => $records,
        ]);
    }

    private function records(string $flow, $date, ?int $serviceId)
    {
        $query = DB::table('atenciones as at')
            ->join('pacientes as pa', 'pa.IdPaciente', '=', 'at.IdPaciente')
            ->leftJoin('servicios as ingreso', 'ingreso.IdServicio', '=', 'at.IdServicioIngreso')
            ->leftJoin('servicios as egreso', 'egreso.IdServicio', '=', 'at.IdServicioEgreso')
            ->leftJoin('especialidades as esp', 'esp.IdEspecialidad', '=', 'at.IdEspecialidadMedico')
            ->select([
                'at.IdAtencion as attention_id', 'at.FechaIngreso as admitted_at', 'at.FechaEgreso as discharged_at',
                'at.IdCamaIngreso as bed_id', 'at.EsPacienteExterno as external_patient',
                'pa.NroDocumento as dni', 'pa.PrimerNombre as first_name', 'pa.SegundoNombre as second_name',
                'pa.ApellidoPaterno as paternal_surname', 'pa.ApellidoMaterno as maternal_surname',
                'ingreso.Nombre as admission_service', 'egreso.Nombre as discharge_service', 'esp.Nombre as specialty',
            ])
            ->orderByDesc('at.FechaIngreso');

        if ($flow === 'externa') {
            $query->where('at.EsPacienteExterno', 1)->whereDate('at.FechaIngreso', $date);
        } else {
            $query->where(function ($q) { $q->whereNull('at.EsPacienteExterno')->orWhere('at.EsPacienteExterno', 0); })
                ->whereNull('at.FechaEgreso');
            if ($flow === 'interna') $query->whereNotNull('at.IdEspecialidadMedico');
        }

        if ($serviceId) $query->where('at.IdServicioIngreso', $serviceId);

        return $query->limit(200)->get();
    }
}
