<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Appointment extends Model { protected $guarded=[]; protected function casts():array{return ['scheduled_at'=>'datetime','confirmed_at'=>'datetime','attention_started_at'=>'datetime','attention_completed_at'=>'datetime'];} public function patient():BelongsTo{return $this->belongsTo(Patient::class);} public function type():BelongsTo{return $this->belongsTo(AppointmentType::class,'appointment_type_id');} public function professional():BelongsTo{return $this->belongsTo(User::class,'professional_id');} public function confirmer():BelongsTo{return $this->belongsTo(User::class,'confirmed_by');} public function area():BelongsTo{return $this->belongsTo(ServiceArea::class,'service_area_id');} public function subarea():BelongsTo{return $this->belongsTo(ServiceSubarea::class,'service_subarea_id');} }
