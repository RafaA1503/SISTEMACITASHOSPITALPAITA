<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomRole extends Model { protected $fillable=['name','modules','active']; protected function casts():array{return ['modules'=>'array','active'=>'boolean'];} public function users():HasMany{return $this->hasMany(User::class);} }
