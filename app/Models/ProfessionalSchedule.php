<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalSchedule extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['scheduled_date' => 'date', 'active' => 'boolean']; }
    public function professional(): BelongsTo { return $this->belongsTo(User::class, 'professional_id'); }
    public function shift(): BelongsTo { return $this->belongsTo(WorkShift::class, 'work_shift_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function area(): BelongsTo { return $this->belongsTo(ServiceArea::class, 'service_area_id'); }
    public function subarea(): BelongsTo { return $this->belongsTo(ServiceSubarea::class, 'service_subarea_id'); }
}
