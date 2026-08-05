<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Appointment extends Model { protected $guarded=[]; protected function casts():array{return ['scheduled_at'=>'datetime'];} public function patient():BelongsTo{return $this->belongsTo(Patient::class);} public function type():BelongsTo{return $this->belongsTo(AppointmentType::class,'appointment_type_id');} public function professional():BelongsTo{return $this->belongsTo(User::class,'professional_id');} }
