<?php

namespace App\Http\Controllers;

use App\Models\CustomRole;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'roles' => CustomRole::withCount('users')->orderBy('name')->get(),
            'users' => User::with('customRole', 'service')->orderBy('name')->get(),
            'services' => Service::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->validatedRole($request);
        CustomRole::create($data + ['active' => true]);

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(Request $request, CustomRole $customRole)
    {
        $this->admin($request);
        $data = $this->validatedRole($request, $customRole);
        $customRole->update($data);

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, CustomRole $customRole)
    {
        $this->admin($request);

        DB::transaction(function () use ($customRole) {
            User::where('custom_role_id', $customRole->id)->update(['custom_role_id' => null]);
            $customRole->delete();
        });

        return back()->with('success', 'Rol eliminado. Los usuarios asociados vuelven a usar su rol base.');
    }

    public function assign(Request $request, User $user)
    {
        $this->admin($request);
        $data = $request->validate([
            'custom_role_id' => 'nullable|exists:custom_roles,id',
            'service_id' => 'nullable|exists:services,id',
        ]);
        $user->update($data);

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
