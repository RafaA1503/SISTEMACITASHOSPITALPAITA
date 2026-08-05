<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ServiceArea extends Model { protected $guarded=[]; protected function casts():array{return ['critical'=>'boolean','food_access'=>'boolean','active'=>'boolean'];} public function service():BelongsTo{return $this->belongsTo(Service::class);} public function subareas():HasMany{return $this->hasMany(ServiceSubarea::class);} }
