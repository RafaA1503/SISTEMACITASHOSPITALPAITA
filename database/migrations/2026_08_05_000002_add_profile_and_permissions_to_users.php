<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
// NOTA: `users` es la tabla real de SIGESA (con datos reales) — nunca se le
// agregan columnas. Todo lo específico de nuestra app (foto, permisos extra,
// rol personalizado) vive en `user_profiles`, una tabla propia 1:1 con `users`.
return new class extends Migration {
    public function up():void{
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('photo_path')->nullable();
            $table->json('permissions')->nullable();
            $table->unsignedBigInteger('custom_role_id')->nullable();
            $table->timestamps();
        });
    }
    public function down():void{
        Schema::dropIfExists('user_profiles');
    }
};
