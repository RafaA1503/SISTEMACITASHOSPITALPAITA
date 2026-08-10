<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Pagina;
use App\Models\Rol;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'administrador', 403);
    }

    public function index(Request $request)
    {
        $this->admin($request);
        $systemId = User::sistemaId();

        return view('auth.roles', [
            'roles' => $this->manualRoles($systemId)
                ->withCount('users')->orderBy('nombreRol')->get(),
            'assignableRoles' => $this->manualRoles($systemId)->orderBy('nombreRol')->get(),
            'users' => User::where('idSistema', $systemId)
                ->with(['persona', 'trabajadorRecord'])
                ->get()->sortBy('name')->values(),
            'pages' => Pagina::where('idSistema', $systemId)->where('estado', true)->with('acciones')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->validatedRole($request);
        $role = Rol::create([
            'nombreRol' => $data['name'],
            'descripcion' => 'Rol creado por administración de Control de Acceso de Pacientes',
            'estado' => 1,
            'idSistema' => User::sistemaId(),
        ]);
        $this->syncPermissions($role, $data);
        AuditLogger::log($request, 'role.created', 'Rol', $role->idRol, $data);

        $role->users_count = 0;
        $html = view('auth.partials.role-card', ['item' => $role])->render();
        if ($response = LiveUpdate::respond($request, 'admin.roles', '.role-list', 'created', $role->idRol, $html)) {
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
            'custom_role_id' => 'required|exists:roles,idRol',
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
        $roleId = (int) $data['custom_role_id'];
        abort_unless($this->manualRoles($systemId)->whereKey($roleId)->exists(), 422, 'Selecciona un rol creado para este sistema.');

        $user = DB::transaction(function () use ($data, $email, $username, $systemId, $roleId, $nombres, $apellidoPaterno, $apellidoMaterno) {
            $documentType = DB::table('tiposdocidentidad')->orderBy('IdDocIdentidad')->value('IdDocIdentidad') ?: 1;
            $persona = Persona::create([
                'nombres' => $nombres,
                'apellidoPaterno' => $apellidoPaterno,
                'apellidoMaterno' => $apellidoMaterno,
                'email' => $email,
                'correoIntitucional' => $email,
                'IdDocIdentidad' => $documentType,
            ]);

            return User::create([
                'correo' => $email,
                'usuario' => $username,
                'password' => $data['password'],
                'idPersona' => $persona->idPersona,
                'idRol' => $roleId,
                'idSistema' => $systemId,
                'idTipoUsers' => 1,
                'Estado' => true,
            ]);
        });

        AuditLogger::log($request, 'user.created', 'User', $user->id, ['email' => $email, 'idRol' => $roleId]);
        $targets = [[
            'selector' => '#roleAssignmentsList',
            'action' => 'created',
            'id' => $user->id,
            'html' => view('auth.partials.role-assignment-row', [
                'item' => $user->fresh(['persona', 'trabajadorRecord']),
                'roles' => $this->manualRoles($systemId)->orderBy('nombreRol')->get(),
            ])->render(),
        ]];
        // Si el rol elegido es uno personalizado, su tarjeta en "Roles creados"
        // queda con el contador desactualizado hasta refrescar — se manda también
        // su HTML actualizado para que se vea al instante.
        if ($roleId) {
            $role = Rol::withCount('users')->find($roleId);
            if ($role && $this->isManualRole($role)) {
                $targets[] = [
                    'selector' => '.role-list',
                    'action' => 'updated',
                    'id' => $role->idRol,
                    'html' => view('auth.partials.role-card', ['item' => $role])->render(),
                ];
            }
        }
        if ($response = LiveUpdate::respondMulti($request, 'admin.roles', $targets)) {
            return $response;
        }

        return back()->with('success', 'Usuario creado y rol asignado correctamente.');
    }

    public function update(Request $request, Rol $role)
    {
        $this->admin($request);
        abort_unless($this->isManualRole($role), 403, 'Este rol no fue creado desde la administración del sistema.');
        $data = $this->validatedRole($request, $role);
        $before = ['name' => $role->nombreRol];
        $role->update(['nombreRol' => $data['name']]);
        $this->syncPermissions($role, $data);
        AuditLogger::log($request, 'role.updated', 'Rol', $role->idRol, ['before' => $before, 'after' => $data]);

        $role->loadCount('users');
        $html = view('auth.partials.role-card', ['item' => $role])->render();
        if ($response = LiveUpdate::respond($request, 'admin.roles', '.role-list', 'updated', $role->idRol, $html)) {
            return $response;
        }

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, Rol $role)
    {
        $this->admin($request);
        abort_unless($this->isManualRole($role), 403, 'Este rol no fue creado desde la administración del sistema.');

        $systemId = User::sistemaId();
        $roleId = $role->idRol;
        $roleName = $role->nombreRol;
        $affectedUsers = User::where('idSistema', $systemId)->where('idRol', $roleId)
            ->with(['persona', 'trabajadorRecord'])->get();
        User::where('idSistema', $systemId)->where('idRol', $roleId)->update(['idRol' => null]);
        $role->delete();
        AuditLogger::log($request, 'role.deleted', 'Rol', $roleId, ['name' => $roleName]);

        $remainingRoles = $this->manualRoles($systemId)->orderBy('nombreRol')->get();
        $targets = [['selector' => '.role-list', 'action' => 'deleted', 'id' => $roleId, 'html' => null]];
        foreach ($affectedUsers as $affectedUser) {
            $targets[] = [
                'selector' => '#roleAssignmentsList',
                'action' => 'updated',
                'id' => $affectedUser->id,
                'html' => view('auth.partials.role-assignment-row', [
                    'item' => $affectedUser->fresh(['persona', 'trabajadorRecord']),
                    'roles' => $remainingRoles,
                ])->render(),
            ];
        }
        if ($response = LiveUpdate::respondMulti($request, 'admin.roles', $targets)) {
            return $response;
        }

        return back()->with('success', 'Rol eliminado. Los usuarios asociados se quedan sin rol asignado.');
    }

    public function assign(Request $request, User $user)
    {
        $this->admin($request);
        abort_unless($user->idSistema === User::sistemaId(), 404);
        $data = $request->validate([
            'custom_role_id' => 'required|exists:roles,idRol',
        ]);
        $before = ['role_id' => $user->idRol];
        $systemId = User::sistemaId();
        $targetRole = $this->manualRoles($systemId)->with('paginas')->find($data['custom_role_id']);
        abort_unless($targetRole, 422, 'Selecciona un rol creado para este sistema.');

        // Sin esto, un administrador se puede quitar su propio acceso de admin
        // (ej. asignándose sin querer un rol personalizado) y quedar bloqueado
        // fuera de esta misma pantalla, sin forma de revertirlo desde la UI.
        if ($user->is($request->user())) {
            abort_unless($targetRole->paginas->contains('descripcion', 'administracion'), 422, 'No puedes asignarte un rol sin acceso a Administración mientras estás conectado.');
        }

        $user->update(['idRol' => $data['custom_role_id']]);

        AuditLogger::log($request, 'user.role_assigned', 'User', $user->id, ['before' => $before, 'after' => $data]);

        $targets = [[
            'selector' => '#roleAssignmentsList',
            'action' => 'updated',
            'id' => $user->id,
            'html' => view('auth.partials.role-assignment-row', [
                'item' => $user->fresh(['persona', 'trabajadorRecord']),
                'roles' => $this->manualRoles($systemId)->orderBy('nombreRol')->get(),
            ])->render(),
        ]];
        // El rol anterior y el nuevo cambian su contador de usuarios asignados —
        // se actualizan sus tarjetas en "Roles creados" (si son personalizadas)
        // para que no quede desfasado hasta refrescar la página.
        $affectedRoleIds = array_filter(array_unique([$before['role_id'], $data['custom_role_id']]));
        $affectedRoles = Rol::withCount('users')->whereIn('idRol', $affectedRoleIds)
            ->get()->filter(fn (Rol $role) => $this->isManualRole($role));
        foreach ($affectedRoles as $affectedRole) {
            $targets[] = [
                'selector' => '.role-list',
                'action' => 'updated',
                'id' => $affectedRole->idRol,
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
            'item' => $user->fresh(['persona', 'trabajadorRecord']),
            'roles' => $this->manualRoles(User::sistemaId())->orderBy('nombreRol')->get(),
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

        abort_if(
            DB::table('access_logs')->where('registered_by', $user->id)->exists(),
            422,
            'Este usuario tiene movimientos de acceso registrados. Para conservar la trazabilidad, solo puede darse de baja.'
        );

        $userId = $user->id;
        $userName = $user->name;
        $roleId = $user->idRol;

        // Solo se borra la cuenta de acceso (users/user_profiles) de este sistema.
        // La ficha de persona (Persona) no se toca: puede estar referenciada por
        // historial clínico o por otros módulos del hospital que comparten la BD.
        DB::transaction(function () use ($user) {
            UserProfile::where('user_id', $user->id)->delete();
            $user->delete();
        });

        AuditLogger::log($request, 'user.deleted', 'User', $userId, ['name' => $userName]);

        $targets = [['selector' => '#roleAssignmentsList', 'action' => 'deleted', 'id' => $userId, 'html' => null]];
        if ($roleId) {
            $role = Rol::withCount('users')->find($roleId);
            if ($role && $this->isManualRole($role)) {
                $targets[] = [
                    'selector' => '.role-list',
                    'action' => 'updated',
                    'id' => $role->idRol,
                    'html' => view('auth.partials.role-card', ['item' => $role])->render(),
                ];
            }
        }
        if ($response = LiveUpdate::respondMulti($request, 'admin.roles', $targets)) {
            return $response;
        }

        return back()->with('success', 'Usuario eliminado permanentemente.');
    }

    private function validatedRole(Request $request, ?Rol $role = null): array
    {
        $systemId = User::sistemaId();

        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('roles', 'nombreRol')->where(fn ($q) => $q->where('idSistema', $systemId))->ignore($role?->idRol, 'idRol'),
            ],
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'integer|exists:paginas,idPagina',
            'action_ids' => 'nullable|array',
            'action_ids.*' => 'integer|exists:acciones,idAccion',
        ]);
    }

    private function syncPermissions(Rol $role, array $data): void
    {
        $pageIds = array_values(array_unique($data['page_ids'] ?? []));
        $actionIds = array_values(array_unique($data['action_ids'] ?? []));
        if ($actionIds) {
            $pageIds = array_values(array_unique(array_merge($pageIds, DB::table('acciones')->whereIn('idAccion', $actionIds)->pluck('idPagina')->all())));
        }
        DB::transaction(function () use ($role, $pageIds, $actionIds): void {
            DB::table('accesos')->where('idRol', $role->idRol)->delete();
            DB::table('accesosacciones')->where('idRol', $role->idRol)->delete();
            foreach ($pageIds as $pageId) DB::table('accesos')->insert(['idRol'=>$role->idRol,'idPagina'=>$pageId,'Estado'=>1]);
            foreach ($actionIds as $actionId) DB::table('accesosacciones')->insert(['idRol'=>$role->idRol,'idAccion'=>$actionId,'Estado'=>1,'FechaActualizado'=>now()]);
        });
    }

    private function manualRoles(int $systemId)
    {
        return Rol::query()
            ->where('idSistema', $systemId)
            ->where('descripcion', 'like', 'Rol creado por administración de Control de Acceso de Pacientes%');
    }

    private function isManualRole(Rol $role): bool
    {
        return str_starts_with((string) $role->descripcion, 'Rol creado por administración de Control de Acceso de Pacientes');
    }
}
