<?php

use Illuminate\Database\Migrations\Migration;

// Reemplazada por 2026_08_08_000000_create_citas_control_acceso_table, que ya
// incluye los campos de confirmación de llegada y el historial de estados
// sobre la tabla nueva `citas_control_acceso` (no sobre `appointments`, que
// ya no existe).
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
