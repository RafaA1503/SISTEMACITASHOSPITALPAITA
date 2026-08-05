<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('services', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('code')->unique();
            $table->enum('category',['laboratorio','imagenes','consulta','administrativo']); $table->string('location')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role',['administrador','portero','admision','profesional','laboratorio','imagenes'])->default('portero')->index();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete(); $table->string('document_number',8)->nullable()->unique(); $table->boolean('active')->default(true);
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id(); $table->string('dni',8)->unique(); $table->string('first_names'); $table->string('last_names'); $table->date('birth_date')->nullable(); $table->enum('sex',['M','F','O'])->nullable(); $table->string('phone')->nullable(); $table->string('insurance')->nullable(); $table->string('emergency_contact')->nullable(); $table->timestamps();
        });
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('code')->unique(); $table->unsignedSmallInteger('duration_minutes')->default(20); $table->text('preparation')->nullable(); $table->boolean('requires_order')->default(true); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->foreignId('patient_id')->constrained()->cascadeOnDelete(); $table->foreignId('appointment_type_id')->constrained(); $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->dateTime('scheduled_at')->index(); $table->enum('status',['programada','confirmada','ingreso','atendida','cancelada','no_asistio'])->default('programada')->index(); $table->string('room')->nullable(); $table->text('notes')->nullable(); $table->timestamps();
            $table->index(['scheduled_at','status'],'idx_appointments_schedule_status');
        });
        Schema::create('medical_orders', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->foreignId('patient_id')->constrained()->cascadeOnDelete(); $table->foreignId('ordered_by')->constrained('users'); $table->foreignId('service_id')->constrained(); $table->date('ordered_at'); $table->string('diagnosis')->nullable(); $table->enum('priority',['normal','urgente'])->default('normal'); $table->enum('status',['pendiente','agendada','procesando','completada','cancelada'])->default('pendiente')->index(); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('medical_order_id')->constrained()->cascadeOnDelete(); $table->foreignId('appointment_type_id')->constrained(); $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete(); $table->string('specimen')->nullable(); $table->text('clinical_indication')->nullable(); $table->enum('status',['pendiente','muestra_tomada','procesando','completado'])->default('pendiente'); $table->timestamps();
        });
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_item_id')->constrained()->cascadeOnDelete(); $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete(); $table->json('values')->nullable(); $table->text('report')->nullable(); $table->enum('status',['borrador','validado','entregado'])->default('borrador'); $table->dateTime('validated_at')->nullable(); $table->timestamps();
        });
        Schema::create('referrals', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->foreignId('patient_id')->constrained()->cascadeOnDelete(); $table->foreignId('requested_by')->constrained('users'); $table->foreignId('from_service_id')->constrained('services'); $table->foreignId('to_service_id')->constrained('services'); $table->string('reason'); $table->enum('priority',['normal','preferente','urgente'])->default('normal'); $table->enum('status',['solicitada','agendada','atendida','rechazada'])->default('solicitada')->index(); $table->dateTime('scheduled_at')->nullable(); $table->timestamps();
        });
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('registered_by')->constrained('users'); $table->enum('movement',['ingreso','salida'])->index(); $table->enum('person_type',['paciente','acompanante','visitante','personal','proveedor']); $table->string('access_point'); $table->string('reason'); $table->unsignedTinyInteger('companions')->default(0); $table->text('notes')->nullable(); $table->dateTime('registered_at')->index(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->string('entity_type'); $table->unsignedBigInteger('entity_id')->nullable(); $table->json('metadata')->nullable(); $table->string('ip_address',45)->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type','entity_id'],'idx_audit_entity');
        });
    }
    public function down(): void {
        Schema::dropIfExists('audit_logs'); Schema::dropIfExists('access_logs'); Schema::dropIfExists('referrals'); Schema::dropIfExists('exam_results'); Schema::dropIfExists('order_items'); Schema::dropIfExists('medical_orders'); Schema::dropIfExists('appointments'); Schema::dropIfExists('appointment_types'); Schema::dropIfExists('patients'); Schema::table('users', fn(Blueprint $table) => $table->dropConstrainedForeignId('service_id')); Schema::table('users', fn(Blueprint $table) => $table->dropColumn(['role','document_number','active'])); Schema::dropIfExists('services');
    }
};
