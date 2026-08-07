<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['starts_at' => 'datetime:H:i', 'ends_at' => 'datetime:H:i', 'active' => 'boolean']; }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function schedules(): HasMany { return $this->hasMany(ProfessionalSchedule::class); }
}
