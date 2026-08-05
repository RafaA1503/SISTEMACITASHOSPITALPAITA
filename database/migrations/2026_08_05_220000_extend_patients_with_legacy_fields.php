<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('paternal_surname', 60)->nullable()->after('last_names');
            $table->string('maternal_surname', 60)->nullable()->after('paternal_surname');
            $table->string('second_name', 60)->nullable()->after('first_names');
            $table->string('third_name', 60)->nullable()->after('second_name');
            $table->string('document_type', 30)->default('DNI')->after('dni');
            $table->string('address', 180)->nullable()->after('phone');
            $table->string('email', 120)->nullable()->after('address');
            $table->string('blood_group', 10)->nullable()->after('sex');
            $table->string('father_name', 100)->nullable();
            $table->string('mother_name', 100)->nullable();
            $table->text('legacy_observation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('patients', fn (Blueprint $table) => $table->dropColumn([
            'paternal_surname','maternal_surname','second_name','third_name','document_type',
            'address','email','blood_group','father_name','mother_name','legacy_observation',
        ]));
    }
};
