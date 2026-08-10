<?php

namespace App\Http\Controllers;

use App\Models\CustomRole;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        ]);
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->validatedRole($request);
        $role = CustomRole::create($data + ['active' => true]);
        AuditLogger::log($request, 'custom_role.created', 'CustomRole', $role->id, $data);

        $role->users_count = 0;
        $html = view('auth.partials.role-card', ['item' => $role])->render();
        if ($response = LiveUpdate::respond($request, 'admin.roles', '.role-list', 'created', $role->id, $html)) {
            return $response;
        }

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function storeUser(Request $request)
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:200',
            'username' => 'nullable|alpha_dash|max:20',
            'password' => 'required|string|min:8|confirmed',
            'custom_role_id' => 'nullable|exists:custom_roles,id',
        ]);

        $systemId = User::sistemaId();
        $email = strtolower(trim($data['email']));
        abort_if(User::where('idSistema', $systemId)->where('correo', $email)->exists(), 422, 'Ya existe un usuario con ese correo.');

        $username = strtolower(trim($data['username'] ?: Str::before($email, '@')));
        abort_if(User::where('idSistema', $systemId)->where('usuario', $username)->exists(), 422, 'El nombre de usuario ya está en uso.');

        $parts = preg_split('/\s+/', trim($data['name']));
        $nombres = array_shift($parts) ?: $data['name'];
        $apellidoPaterno = array_shift($parts);
        $apellidoMaterno = implode(' ', $parts) ?: null;
        // El rol base es solo un punto de partida razonable (y ni siquiera se usa si
        // el usuario tiene un rol personalizado activo, ver User::canAccessModule).
        // Que falte "portero" en la tabla roles no debe bloquear crear una cuenta.
        $baseRoleId = Rol::where('idSistema', $systemId)->where('nombreRol', 'portero')->value('idRol');

        $user = DB::transaction(function () use ($data, $email, $username, $systemId, $baseRoleId, $nombres, $apellidoPaterno, $apellidoMaterno) {
            $documentType = DB::table('tiposdocidentidad')->orderBy('IdDocIdentidad')->value('IdDocIdentidad') ?: 1;
            $persona = Persona::create([
                'nombres' => $nombres,
                'apellidoPaterno' => $apellidoPaterno,
                'apellidoMaterno' => $apellidoMaterno,
                'email' => $email,
                'correoIntitucional' => $email,
                'IdDocIdentidad' => $documentType,
            ]);
            $user = User::create([
                'correo' => $email,
                'usuario' => $username,
                'password' => $data['password'],
                'idPersona' => $persona->idPersona,
                'idRol' => $baseRoleId,
                'idSistema' => $systemId,
                'idTipoUsers' => 1,
                'Estado' => true,
            ]);
            UserProfile::updateOrCreate(['user_id' => $user->id], ['custom_role_id' => $data['custom_role_id'] ?? null]);
            return $user;
        });

        AuditLogger::log($request, 'user.created', 'User', $user->id, ['email' => $email, 'custom_role_id' => $data['custom_role_id'] ?? null]);
        $targets = [[
            'selector' => '#roleAssignmentsList',
            'action' => 'created',
            'id' => $user->id,
            'html' => view('auth.partials.role-assignment-row', [
                'item' => $user->fresh(['persona', 'profile.customRole', 'trabajadorRecord']),
                'roles' => CustomRole::orderBy('name')->get(),
            ])->render(),
        ]];
        // Si se asignó un rol personalizado al crear, su tarjeta en "Roles creados"
        // queda con el contador de usuarios desactualizado hasta refrescar — se
        // manda también su HTML actualizado para que se vea al instante.
        if (! empty($data['custom_role_id'])) {
            $customRole = CustomRole::withCount('profiles as users_count')->find($data['custom_role_id']);
            if ($customRole) {
                $targets[] = [
                    'selector' => '.role-list',
                    'action' => 'updated',
                    'id' => $customRole->id,
                    'html' => view('auth.partials.role-card', ['item' => $customRole])->render(),
                ];
            }
        }
        if ($response = LiveUpdate::respondMulti($request, 'admin.roles', $targets)) {
            return $response;
        }
        return back()->with('success', 'Usuario creado y rol asignado correctamente.');
    }

    public function update(Request $request, CustomRole $customRole)
    {
        $this->admin($request);
        $data = $this->validatedRole($request, $customRole);
        $before = $customRole->only(['name', 'modules']);
        $customRole->update($data);
        AuditLogger::log($request, 'custom_role.updated', 'CustomRole', $customRole->id, ['before' => $before, 'after' => $data]);

        $customRole->loadCount('profiles as users_count');
        $html = view('auth.partials.role-card', ['item' => $customRole])->render();
        if ($response = LiveUpdate::respond($request, 'admin.roles', '.role-list', 'updated', $customRole->id, $html)) {
            return $response;
        }

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, CustomRole $customRole)
    {
        $this->admin($request);

        $roleId = $customRole->id;
        $roleName = $customRole->name;
        $affectedUsers = User::where('idSistema', User::sistemaId())
            ->whereHas('profile', fn ($query) => $query->where('custom_role_id', $customRole->id))
            ->with(['persona', 'profile.customRole', 'trabajadorRecord'])
            ->get();
        UserProfile::where('custom_role_id', $customRole->id)->update(['custom_role_id' => null]);
        $customRole->delete();
        AuditLogger::log($request, 'custom_role.deleted', 'CustomRole', $roleId, ['name' => $roleName]);

        $remainingRoles = CustomRole::orderBy('name')->get();
        $targets = [['selector' => '.role-list', 'action' => 'deleted', 'id' => $roleId, 'html' => null]];
        foreach ($affectedUsers as $affectedUser) {
            $targets[] = [
                'selector' => '#roleAssignmentsList',
                'action' => 'updated',
                'id' => $affectedUser->id,
                'html' => view('auth.partials.role-assignment-row', [
                    'item' => $affectedUser->fresh(['persona', 'profile.customRole', 'trabajadorRecord']),
                    'roles' => $remainingRoles,
                ])->render(),
            ];
        }
        if ($response = LiveUpdate::respondMulti($request, 'admin.roles', $targets)) {
            return $response;
        }

        return back()->with('success', 'Rol eliminado. Los usuarios asociados vuelven a usar su rol base.');
    }

    public function assign(Request $request, User $user)
    {
        $this->admin($request);
        abort_unless($user->idSistema === User::sistemaId(), 404);
        $data = $request->validate([
            'custom_role_id' => 'nullable|exists:custom_roles,id',
        ]);
        $before = ['custom_role_id' => $user->resolvedCustomRole()?->id];

        UserProfile::updateOrCreate(['user_id' => $user->id], ['custom_role_id' => $data['custom_role_id'] ?? null]);

        AuditLogger::log($request, 'user.role_assigned', 'User', $user->id, ['before' => $before, 'after' => $data]);

        $targets = [[
            'selector' => '#roleAssignmentsList',
            'action' => 'updated',
            'id' => $user->id,
            'html' => view('auth.partials.role-assignment-row', [
                'item' => $user->fresh(['persona', 'profile.customRole', 'trabajadorRecord']),
                'roles' => CustomRole::orderBy('name')->get(),
            ])->render(),
        ]];
        // El rol anterior y el nuevo cambian su contador de usuarios asignados —
        // se actualizan sus tarjetas en "Roles creados" para que no quede desfasado
        // hasta refrescar la página.
        $affectedRoleIds = array_filter(array_unique([$before['custom_role_id'], $data['custom_role_id'] ?? null]));
        foreach (CustomRole::withCount('profiles as users_count')->whereIn('id', $affectedRoleIds)->get() as $affectedRole) {
            $targets[] = [
                'selector' => '.role-list',
                'action' => 'updated',
                'id' => $affectedRole->id,
                'html' => view('auth.partials.role-card', ['item' => $affectedRole])->render(),
            ];
        }
        if ($response = LiveUpdate::respondMulti($request, 'admin.roles', $targets)) {
            return $response;
        }

        return back()->with('success', 'Rol asignado correctamente.');
    }

    public function setUserStatus(Request $request, User $user)
    {
        $this->admin($request);
        abort_unless($user->idSistema === User::sistemaId(), 404);
        abort_if($user->is($request->user()) && ! $request->boolean('active'), 422, 'No puedes dar de baja a tu propia cuenta mientras estás conectado.');

        $active = $request->boolean('active');
        $user->update(['Estado' => $active]);
        AuditLogger::log($request, $active ? 'user.reactivated' : 'user.deactivated', 'User', $user->id, ['active' => $active]);

        $html = view('auth.partials.role-assignment-row', [
            'item' => $user->fresh(['persona', 'profile.customRole', 'trabajadorRecord']),
            'roles' => CustomRole::orderBy('name')->get(),
        ])->render();
        if ($response = LiveUpdate::respond($request, 'admin.roles', '#roleAssignmentsList', 'updated', $user->id, $html)) {
            return $response;
        }

        return back()->with('success', $active ? 'Usuario reactivado.' : 'Usuario dado de baja: ya no puede acceder al sistema.');
    }

    public function destroyUser(Request $request, User $user)
    {
        $this->admin($request);
        abort_unless($user->idSistema === User::sistemaId(), 404);
        abort_if($user->is($request->user()), 422, 'No puedes eliminar tu propia cuenta mientras estás conectado.');

        $userId = $user->id;
        $userName = $user->name;
        $customRoleId = $user->resolvedCustomRole()?->id;

        // Solo se borra la cuenta de acceso (users/user_profiles) de este sistema.
        // La ficha de persona (Persona) no se toca: puede estar referenciada por
        // historial clínico o por otros módulos del hospital que comparten la BD.
        DB::transaction(function () use ($user) {
            UserProfile::where('user_id', $user->id)->delete();
            $user->delete();
        });

        AuditLogger::log($request, 'user.deleted', 'User', $userId, ['name' => $userName]);

        $targets = [['selector' => '#roleAssignmentsList', 'action' => 'deleted', 'id' => $userId, 'html' => null]];
        if ($customRoleId) {
            $customRole = CustomRole::withCount('profiles as users_count')->find($customRoleId);
            if ($customRole) {
                $targets[] = [
                    'selector' => '.role-list',
                    'action' => 'updated',
                    'id' => $customRole->id,
                    'html' => view('auth.partials.role-card', ['item' => $customRole])->render(),
                ];
            }
        }
        if ($response = LiveUpdate::respondMulti($request, 'admin.roles', $targets)) {
            return $response;
        }

        return back()->with('success', 'Usuario eliminado permanentemente.');
    }

    private function validatedRole(Request $request, ?CustomRole $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:custom_roles,name'.($role ? ','.$role->id : '')],
            'modules' => 'required|array|min:1',
            'modules.*' => 'in:portero,administrador',
        ]);
    }
}
