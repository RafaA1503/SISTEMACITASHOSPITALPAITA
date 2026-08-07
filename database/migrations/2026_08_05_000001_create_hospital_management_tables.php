<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // NOTA: esta app ahora se conecta directo a la base real de SIGESA.
        // `services`/`appointment_types` son un catálogo propio (alimentado por
        // hospital:import-legacy-catalog); NUNCA se crean/alteran aquí `users`,
        // `patients` ni `appointments` porque esas tablas legacy ya existen con
        // datos reales o son reemplazadas por `pacientes`/`citas_control_acceso`.
        Schema::create('services', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('code')->unique();
            $table->enum('category',['laboratorio','imagenes','consulta','administrativo']); $table->string('location')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('code')->unique(); $table->unsignedSmallInteger('duration_minutes')->default(20); $table->text('preparation')->nullable(); $table->boolean('requires_order')->default(true); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->string('entity_type'); $table->unsignedBigInteger('entity_id')->nullable(); $table->json('metadata')->nullable(); $table->string('ip_address',45)->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type','entity_id'],'idx_audit_entity');
        });
    }
    public function down(): void {
        Schema::dropIfExists('audit_logs'); Schema::dropIfExists('appointment_types'); Schema::dropIfExists('services');
    }
};
