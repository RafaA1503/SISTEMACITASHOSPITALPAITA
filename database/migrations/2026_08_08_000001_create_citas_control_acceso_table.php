<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla de citas propia del módulo de Portería. No usa `citas`/`atenciones`
// (legacy) porque esas exigen `atenciones.IdMedicoIngreso` -> `medicos` y
// `atenciones.IdCuentaAtencion` -> cuenta de facturación, ambas vacías y sin
// uso real en este hospital: forzar esa cadena implicaría inventar médicos y
// cuentas de facturación falsos solo para poder guardar una cita. En cambio,
// esta tabla referencia directo `pacientes.IdPaciente` y `servicios.IdServicio`
// (sin FK dura por diferencia de tipos int/unsigned bigint), y agrega lo que
// no existe en ninguna tabla legacy: confirmación de llegada del paciente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas_control_acceso', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->integer('paciente_id'); // pacientes.IdPaciente
            $table->foreignId('appointment_type_id')->constrained();
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_subarea_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('trabajador_id')->nullable(); // trabajador.idTrabajador (profesional asignado)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->enum('status', ['programada', 'confirmada', 'ingreso', 'atendida', 'cancelada', 'no_asistio'])->default('programada')->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable()->index();
            $table->dateTime('attention_started_at')->nullable();
            $table->dateTime('attention_completed_at')->nullable();
            $table->string('room')->nullable();
            $table->string('access_point', 80)->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['scheduled_at', 'status'], 'idx_citas_control_acceso_schedule_status');
        });

        Schema::create('appointment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('citas_control_acceso')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['appointment_id', 'created_at']);
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('paciente_id')->nullable(); // pacientes.IdPaciente
            $table->foreignId('appointment_id')->nullable()->constrained('citas_control_acceso')->nullOnDelete();
            $table->foreignId('registered_by')->constrained('users');
            $table->enum('movement', ['ingreso', 'salida'])->index();
            $table->enum('person_type', ['paciente', 'acompanante', 'visitante', 'personal', 'proveedor']);
            $table->string('access_point');
            $table->string('reason');
            $table->unsignedTinyInteger('companions')->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('registered_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('appointment_status_history');
        Schema::dropIfExists('citas_control_acceso');
    }
};
