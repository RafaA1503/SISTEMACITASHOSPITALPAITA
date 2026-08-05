<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Service extends Model { protected $guarded=[]; protected function casts():array{return ['active'=>'boolean','report_enabled'=>'boolean'];} public function appointmentTypes():HasMany{return $this->hasMany(AppointmentType::class);} public function areas():HasMany{return $this->hasMany(ServiceArea::class);} public function specialty():BelongsTo{return $this->belongsTo(Specialty::class);} public function type():BelongsTo{return $this->belongsTo(ServiceType::class,'service_type_id');} }
