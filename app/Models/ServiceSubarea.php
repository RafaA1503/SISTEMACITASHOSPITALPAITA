<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ServiceSubarea extends Model { protected $guarded=[]; protected function casts():array{return ['food_access'=>'boolean','active'=>'boolean'];} public function area():BelongsTo{return $this->belongsTo(ServiceArea::class,'service_area_id');} }
