<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Service extends Model { protected $guarded=[]; protected function casts():array{return ['active'=>'boolean'];} public function appointmentTypes():HasMany{return $this->hasMany(AppointmentType::class);} }
