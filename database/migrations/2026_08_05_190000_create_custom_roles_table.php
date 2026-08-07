<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('modules');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        // `custom_role_id` vive en `user_profiles` (ver 2026_08_05_000002), no en `users`.
    }
    public function down(): void
    {
        Schema::dropIfExists('custom_roles');
    }
};
