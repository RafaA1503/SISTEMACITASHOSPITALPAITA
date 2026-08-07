<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove the old generated SIG-* values.  IdServicio is kept in legacy_id
     * and is the only imported identifier shown or used by the application.
     */
    public function up(): void
    {
        DB::table('services')->where('code', 'like', 'SIG-%')->orderBy('id')->get()->each(
            fn ($service) => DB::table('services')->where('id', $service->id)->update([
                'code' => 'legacy-service-'.$service->legacy_id,
                'updated_at' => now(),
            ])
        );

        DB::table('appointment_types')
            ->join('services', 'services.id', '=', 'appointment_types.service_id')
            ->where('appointment_types.code', 'like', 'SIG-A-%')
            ->orderBy('appointment_types.id')
            ->select('appointment_types.id', 'services.legacy_id')
            ->get()
            ->each(fn ($type) => DB::table('appointment_types')->where('id', $type->id)->update([
                'code' => 'legacy-attention-'.$type->legacy_id,
                'updated_at' => now(),
            ])
        );
    }

    public function down(): void
    {
        // The generated SIG values must not be restored.
    }
};
