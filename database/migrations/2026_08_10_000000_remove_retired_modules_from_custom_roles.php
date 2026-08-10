<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('custom_roles')->orderBy('id')->each(function (object $role): void {
            $modules = json_decode($role->modules ?? '[]', true) ?: [];
            $modules = array_values(array_intersect($modules, ['portero', 'administrador']));

            // Un perfil sin módulo quedaría bloqueado luego de retirar Citas y Atención.
            if ($modules === []) $modules = ['portero'];

            DB::table('custom_roles')->where('id', $role->id)->update([
                'modules' => json_encode($modules),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // No se restauran permisos eliminados: no se puede saber cuáles tenía cada rol.
    }
};
