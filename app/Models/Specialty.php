<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Specialty extends Model { protected $guarded=[]; protected function casts():array{return ['allows_referral'=>'boolean','active'=>'boolean'];} }
