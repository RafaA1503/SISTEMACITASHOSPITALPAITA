<?php

namespace App\Http\Controllers;

use App\Models\CustomRole;
use App\Models\Service;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'administrador', 403);
    }

    public function index(Request $request)
    {
        $this->admin($request);

        return view('auth.roles', [
            'roles' => CustomRole::withCount('profiles as users_count')->orderBy('name')->get(),
            'users' => User::where('idSistema', User::sistemaId())
                ->with(['persona', 'profile.customRole', 'trabajadorRecord'])
                ->get()->sortBy('name')->values(),
            'services' => Service::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->validatedRole($request);
        $role = CustomRole::create($data + ['active' => true]);
        AuditLogger::log($request, 'custom_role.created', 'CustomRole', $role->id, $data);

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(Request $request, CustomRole $customRole)
    {
        $this->admin($request);
        $data = $this->validatedRole($request, $customRole);
        $before = $customRole->only(['name', 'modules']);
        $customRole->update($data);
        AuditLogger::log($request, 'custom_role.updated', 'CustomRole', $customRole->id, ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, CustomRole $customRole)
    {
        $this->admin($request);

        $roleId = $customRole->id;
        $roleName = $customRole->name;
        UserProfile::where('custom_role_id', $customRole->id)->update(['custom_role_id' => null]);
        $customRole->delete();
        AuditLogger::log($request, 'custom_role.deleted', 'CustomRole', $roleId, ['name' => $roleName]);

        return back()->with('success', 'Rol eliminado. Los usuarios asociados vuelven a usar su rol base.');
    }

    public function assign(Request $request, User $user)
    {
        $this->admin($request);
        abort_unless($user->idSistema === User::sistemaId(), 404);
        $data = $request->validate([
            'custom_role_id' => 'nullable|exists:custom_roles,id',
            'service_id' => 'nullable|exists:services,id',
        ]);
        $before = ['custom_role_id' => $user->resolvedCustomRole()?->id, 'service_id' => $user->service_id];

        UserProfile::updateOrCreate(['user_id' => $user->id], ['custom_role_id' => $data['custom_role_id'] ?? null]);

        if (! empty($data['service_id']) && $user->idPersona) {
            $legacyServiceId = Service::find($data['service_id'])?->legacy_id;
            if ($legacyServiceId) {
                Trabajador::updateOrCreate(['idPersona' => $user->idPersona], ['idServicio' => $legacyServiceId, 'estado' => 1]);
            }
        }

        AuditLogger::log($request, 'user.role_assigned', 'User', $user->id, ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Rol y servicio asignados.');
    }

    private function validatedRole(Request $request, ?CustomRole $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:custom_roles,name'.($role ? ','.$role->id : '')],
            'modules' => 'required|array|min:1',
            'modules.*' => 'in:portero,citas,servicio,administrador',
        ]);
    }
}
