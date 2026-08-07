<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// La tabla `pacientes` del dump legacy está vacía y su PK `IdPaciente` no
// tiene AUTO_INCREMENT (a diferencia de `personas`/`trabajador`), probablemente
// porque el sistema original generaba el id por aplicación. Como la tabla no
// tiene datos reales, es seguro agregar AUTO_INCREMENT para que Eloquent
// pueda insertar pacientes nuevos igual que en el resto de tablas legacy.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pacientes MODIFY IdPaciente INT(11) NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pacientes MODIFY IdPaciente INT(11) NOT NULL');
    }
};
