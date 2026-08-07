<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Rol;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ProfessionalSchedule;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class AdminCatalogController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'administrador', 403);
    }

    public function index(Request $request)
    {
        $this->admin($request);

        return view('auth.catalog', [
            'services' => Service::with(['type', 'specialty'])
                ->withCount(['appointmentTypes', 'areas'])
                ->whereNotNull('legacy_id')
                ->orderBy('name')
                ->get(),
            'professionals' => User::withRoles(['profesional', 'laboratorio', 'imagenes'])->filter(fn ($u) => $u->service_id !== null)->values(),
            'areas' => ServiceArea::with('service')->where('active', true)->orderBy('name')->get(),
            'shifts' => WorkShift::with('service')->where('active', true)->orderBy('name')->get(),
            'schedules' => ProfessionalSchedule::with(['trabajador.persona', 'service', 'area', 'shift'])
                ->whereDate('scheduled_date', '>=', today())
                ->orderBy('scheduled_date')
                ->get(),
        ]);
    }

    public function storeProfessional(Request $request)
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'dni' => 'required|digits:8',
            'email' => 'required|email|unique:users,correo',
            'password' => 'required|string|min:8',
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::findOrFail($data['service_id']);
        abort_unless($service->legacy_id, 422, 'El servicio seleccionado no tiene un IdServicio legacy para vincular al trabajador.');

        $persona = Persona::firstOrCreate(['nroDoc' => $data['dni']], ['nombres' => $data['name'], 'IdDocIdentidad' => 1]);
        $trabajador = Trabajador::updateOrCreate(['idPersona' => $persona->idPersona], ['idServicio' => $service->legacy_id, 'estado' => 1]);
        $rolId = Rol::where('nombreRol', 'profesional')->where('idSistema', User::sistemaId())->value('idRol');

        $professional = User::create([
            'correo' => strtolower(trim($data['email'])),
            'usuario' => $data['dni'],
            'password' => $data['password'],
            'idPersona' => $persona->idPersona,
            'idRol' => $rolId,
            'idSistema' => User::sistemaId(),
            'idTipoUsers' => 1,
            'Estado' => 1,
        ]);
        AuditLogger::log($request, 'professional.created', 'User', $professional->id, ['service_id' => $data['service_id'], 'trabajador_id' => $trabajador->idTrabajador]);

        return back()->with('success', 'Profesional creado y asignado al servicio.');
    }

    public function storeSchedule(Request $request)
    {
        $this->admin($request);
        $data = $request->validate([
            'professional_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'service_area_id' => 'nullable|exists:service_areas,id',
            'work_shift_id' => 'required|exists:work_shifts,id',
            'scheduled_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $professional = User::find($data['professional_id']);
        if (! $professional || ! $professional->active) {
            return back()->withInput()->withErrors('El profesional no está activo.');
        }
        $trabajadorId = $professional->trabajadorRecord?->idTrabajador;
        if (! $trabajadorId) {
            return back()->withInput()->withErrors('El profesional no tiene un registro de trabajador vinculado.');
        }
        if (! WorkShift::whereKey($data['work_shift_id'])->where(fn ($query) => $query->whereNull('service_id')->orWhere('service_id', $data['service_id']))->exists()) {
            return back()->withInput()->withErrors('La jornada no corresponde al servicio.');
        }
        if (! empty($data['service_area_id']) && ! ServiceArea::whereKey($data['service_area_id'])->where('service_id', $data['service_id'])->exists()) {
            return back()->withInput()->withErrors('El área no pertenece al servicio.');
        }

        $schedule = ProfessionalSchedule::updateOrCreate([
            'trabajador_id' => $trabajadorId,
            'work_shift_id' => $data['work_shift_id'],
            'scheduled_date' => $data['scheduled_date'],
        ], [
            'service_id' => $data['service_id'],
            'service_area_id' => $data['service_area_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'active' => true,
        ]);
        AuditLogger::log($request, 'professional_schedule.created', 'ProfessionalSchedule', $schedule->id, $data);

        return back()->with('success', 'Turno profesional registrado. Ya puede recibir citas de ese servicio en la fecha programada.');
    }
}
