<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('confirmed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable()->after('scheduled_at')->index();
            $table->dateTime('attention_started_at')->nullable()->after('confirmed_at');
            $table->dateTime('attention_completed_at')->nullable()->after('attention_started_at');
            $table->string('access_point', 80)->nullable()->after('room');
            $table->text('cancellation_reason')->nullable()->after('notes');
        });

        Schema::create('appointment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_history');
        Schema::table('appointments', fn (Blueprint $table) => $table->dropConstrainedForeignId('confirmed_by'));
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn(['confirmed_at','attention_started_at','attention_completed_at','access_point','cancellation_reason']));
    }
};
