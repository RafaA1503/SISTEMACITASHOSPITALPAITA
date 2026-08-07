<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('name', 150);
            $table->unsignedSmallInteger('average_attention_minutes')->nullable();
            $table->string('minsa_code', 20)->nullable();
            $table->boolean('allows_referral')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('name', 180);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
            $table->foreignId('specialty_id')->nullable()->after('code')->constrained()->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->after('specialty_id')->constrained()->nullOnDelete();
            $table->boolean('report_enabled')->default(false)->after('active');
            $table->text('notes')->nullable();
        });

        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->boolean('critical')->default(false);
            $table->boolean('food_access')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_subareas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('service_area_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->boolean('food_access')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // `patients`/`appointments` ya no existen: se reemplazan por `pacientes`
        // (legacy) y `citas_control_acceso` (nueva, ver 2026_08_08_000000).
    }

    public function down(): void
    {
        Schema::dropIfExists('service_subareas');
        Schema::dropIfExists('service_areas');
        Schema::table('services', fn (Blueprint $table) => $table->dropConstrainedForeignId('service_type_id'));
        Schema::table('services', fn (Blueprint $table) => $table->dropConstrainedForeignId('specialty_id'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['legacy_id', 'report_enabled', 'notes']));
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('specialties');
        Schema::dropIfExists('service_types');
    }
};
