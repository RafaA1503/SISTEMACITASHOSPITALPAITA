<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registra "Control de Acceso de Pacientes" como un sistema más dentro del
// esquema multi-sistema que SIGESA ya usa (tabla `sistemas` + `roles`
// filtrados por idSistema, igual que resultMedic/LibroReclamaciones/
// CasillaEletronica). No se toca la tabla `users` en ningún momento: los
// nuevos logins son filas nuevas en `users` (mismo esquema, solo INSERT).
return new class extends Migration
{
    private const SISTEMA_NOMBRE = 'ControlAccesoPacientes';

    public function up(): void
    {
        $sistemaId = DB::table('sistemas')->where('nombre', self::SISTEMA_NOMBRE)->value('idSistema');
        if (! $sistemaId) {
            $sistemaId = DB::table('sistemas')->insertGetId([
                'nombre' => self::SISTEMA_NOMBRE,
                'descripcion' => 'Portería, registro de citas, atención profesional y turnos de pacientes',
                'estado' => 1,
            ], 'idSistema');
        }

    }

    public function down(): void
    {
        $sistemaId = DB::table('sistemas')->where('nombre', self::SISTEMA_NOMBRE)->value('idSistema');
        if ($sistemaId) {
            DB::table('users')->where('idSistema', $sistemaId)->delete();
            DB::table('roles')->where('idSistema', $sistemaId)->delete();
            DB::table('sistemas')->where('idSistema', $sistemaId)->delete();
        }
    }
};
