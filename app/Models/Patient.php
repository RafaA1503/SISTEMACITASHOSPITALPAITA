<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Patient extends Model { protected $guarded=[]; protected function casts():array{return ['birth_date'=>'date'];} public function appointments():HasMany{return $this->hasMany(Appointment::class);} public function getFullNameAttribute():string{return "$this->first_names $this->last_names";} }
