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
use App\Support\LiveUpdate;
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

        $html = view('auth.partials.professional-card', ['professional' => $professional])->render();
        if ($response = LiveUpdate::respond($request, 'admin.professionals', '#professionalsList', 'created', $professional->id, $html)) {
            return $response;
        }

        return back()->with('success', 'Profesional creado y asignado al servicio.');
    }

    private function validateSchedule(Request $request): array
    {
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
            throw new \RuntimeException('El profesional no está activo.');
        }
        $trabajadorId = $professional->trabajadorRecord?->idTrabajador;
        if (! $trabajadorId) {
            throw new \RuntimeException('El profesional no tiene un registro de trabajador vinculado.');
        }
        if (! WorkShift::whereKey($data['work_shift_id'])->where(fn ($query) => $query->whereNull('service_id')->orWhere('service_id', $data['service_id']))->exists()) {
            throw new \RuntimeException('La jornada no corresponde al servicio.');
        }
        if (! empty($data['service_area_id']) && ! ServiceArea::whereKey($data['service_area_id'])->where('service_id', $data['service_id'])->exists()) {
            throw new \RuntimeException('El área no pertenece al servicio.');
        }

        $data['trabajador_id'] = $trabajadorId;

        return $data;
    }

    private function scheduleError(Request $request, \RuntimeException $e)
    {
        return $request->wantsJson()
            ? response()->json(['message' => $e->getMessage()], 422)
            : back()->withInput()->withErrors($e->getMessage());
    }

    public function storeSchedule(Request $request)
    {
        $this->admin($request);
        try {
            $data = $this->validateSchedule($request);
        } catch (\RuntimeException $e) {
            return $this->scheduleError($request, $e);
        }

        $schedule = ProfessionalSchedule::updateOrCreate([
            'trabajador_id' => $data['trabajador_id'],
            'work_shift_id' => $data['work_shift_id'],
            'scheduled_date' => $data['scheduled_date'],
        ], [
            'service_id' => $data['service_id'],
            'service_area_id' => $data['service_area_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'active' => true,
        ]);
        AuditLogger::log($request, 'professional_schedule.created', 'ProfessionalSchedule', $schedule->id, $data);

        $html = view('auth.partials.schedule-row', ['schedule' => $schedule->load(['trabajador.persona', 'service', 'area', 'shift'])])->render();
        if ($response = LiveUpdate::respond($request, 'admin.schedules', '#schedulesTableBody', 'created', $schedule->id, $html)) {
            return $response;
        }

        return back()->with('success', 'Turno profesional registrado. Ya puede recibir citas de ese servicio en la fecha programada.');
    }

    public function updateSchedule(Request $request, ProfessionalSchedule $schedule)
    {
        $this->admin($request);
        try {
            $data = $this->validateSchedule($request);
        } catch (\RuntimeException $e) {
            return $this->scheduleError($request, $e);
        }

        $before = $schedule->only(['trabajador_id', 'service_id', 'service_area_id', 'work_shift_id', 'scheduled_date', 'notes']);
        $schedule->update([
            'trabajador_id' => $data['trabajador_id'],
            'service_id' => $data['service_id'],
            'service_area_id' => $data['service_area_id'] ?? null,
            'work_shift_id' => $data['work_shift_id'],
            'scheduled_date' => $data['scheduled_date'],
            'notes' => $data['notes'] ?? null,
        ]);
        AuditLogger::log($request, 'professional_schedule.updated', 'ProfessionalSchedule', $schedule->id, ['before' => $before, 'after' => $data]);

        $html = view('auth.partials.schedule-row', ['schedule' => $schedule->fresh(['trabajador.persona', 'service', 'area', 'shift'])])->render();
        if ($response = LiveUpdate::respond($request, 'admin.schedules', '#schedulesTableBody', 'updated', $schedule->id, $html)) {
            return $response;
        }

        return back()->with('success', 'Turno actualizado correctamente.');
    }

    public function destroySchedule(Request $request, ProfessionalSchedule $schedule)
    {
        $this->admin($request);
        $scheduleId = $schedule->id;
        AuditLogger::log($request, 'professional_schedule.deleted', 'ProfessionalSchedule', $scheduleId, $schedule->only(['trabajador_id', 'service_id', 'scheduled_date']));
        $schedule->delete();

        if ($response = LiveUpdate::respond($request, 'admin.schedules', '#schedulesTableBody', 'deleted', $scheduleId, null)) {
            return $response;
        }

        return back()->with('success', 'Turno eliminado.');
    }
}
