<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ProfessionalSchedule;
use App\Models\User;
use App\Models\WorkShift;
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
            'professionals' => User::with('service')->whereIn('role', ['profesional', 'laboratorio', 'imagenes'])->whereNotNull('service_id')->orderBy('name')->get(),
            'areas' => ServiceArea::with('service')->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeProfessional(Request $request)
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'service_id' => 'required|exists:services,id',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
            'role' => 'profesional',
            'service_id' => $data['service_id'],
            'active' => true,
        ]);

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

        if (! User::whereKey($data['professional_id'])->whereIn('role', ['profesional', 'laboratorio', 'imagenes'])->where('active', true)->exists()) abort(422, 'El profesional no está activo.');
        if (! WorkShift::whereKey($data['work_shift_id'])->where(fn ($query) => $query->whereNull('service_id')->orWhere('service_id', $data['service_id']))->exists()) abort(422, 'La jornada no corresponde al servicio.');
        if (! empty($data['service_area_id'])) abort_unless(ServiceArea::whereKey($data['service_area_id'])->where('service_id', $data['service_id'])->exists(), 422, 'El área no pertenece al servicio.');

        ProfessionalSchedule::updateOrCreate([
            'professional_id' => $data['professional_id'],
            'work_shift_id' => $data['work_shift_id'],
            'scheduled_date' => $data['scheduled_date'],
        ], $data + ['active' => true]);

        return back()->with('success', 'Turno profesional registrado. Ya puede recibir citas de ese servicio en la fecha programada.');
    }
}
