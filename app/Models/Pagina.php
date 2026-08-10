<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Pagina extends Model { protected $table='paginas'; protected $primaryKey='idPagina'; public $timestamps=false; protected $guarded=[]; public function acciones():HasMany{return $this->hasMany(Accion::class,'idPagina','idPagina')->where('estado',true);} }
