<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->unique(); // jornadaslaborales.idJornada
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 250); // descripcion
            $table->string('abbreviation', 10)->nullable(); // abreviatura
            $table->time('starts_at')->nullable(); // horaEntrada
            $table->time('ends_at')->nullable(); // horaSalida
            $table->boolean('active')->default(true); // estado
            $table->timestamps();
        });

        Schema::create('professional_schedules', function (Blueprint $table) {
            $table->id();
            // `trabajador_id` referencia trabajador.idTrabajador (tabla legacy real,
            // con datos) sin FK dura por diferencia de tipos int/bigint — la
            // integridad se valida en la capa de aplicación.
            $table->unsignedInteger('trabajador_id');
            $table->foreignId('work_shift_id')->constrained()->restrictOnDelete(); // idJornada
            $table->foreignId('service_id')->constrained()->restrictOnDelete(); // idServicio
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete(); // idArea
            $table->foreignId('service_subarea_id')->nullable()->constrained()->nullOnDelete(); // idSubArea
            $table->date('scheduled_date'); // fecha
            $table->boolean('active')->default(true); // activo
            $table->text('notes')->nullable(); // observacion
            $table->timestamps();
            $table->unique(['trabajador_id', 'work_shift_id', 'scheduled_date'], 'professional_schedule_shift_date_unique');
            $table->index(['service_id', 'scheduled_date', 'active'], 'professional_schedule_service_date_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_schedules');
        Schema::dropIfExists('work_shifts');
    }
};
