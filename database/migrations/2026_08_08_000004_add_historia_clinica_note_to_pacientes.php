<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `NroHistoriaClinica` es int(11) en el legacy, pero los números de historia
// clínica reales usan formatos alfanuméricos (ej. "h-2222"). Se agrega una
// columna de texto separada para no perder ese formato; tabla vacía, sin riesgo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('historia_clinica_note', 30)->nullable()->after('insurance_note');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', fn (Blueprint $table) => $table->dropColumn('historia_clinica_note'));
    }
};
