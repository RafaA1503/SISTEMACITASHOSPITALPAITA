<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('insurance_note', 100)->nullable()->after('emergency_contact');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', fn (Blueprint $table) => $table->dropColumn('insurance_note'));
    }
};
