<?php
namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;
class DniLookupController extends Controller {
 public function __invoke(Request $request, string $dni): JsonResponse {
  $validated=validator(['dni'=>$dni],['dni'=>['required','digits:8']])->validate(); $url=config('services.codart.url'); $token=config('services.codart.token');
  if(!$url||!$token) return response()->json(['message'=>'La consulta de DNI no está configurada.'],503);
  try {
   $endpoint=preg_replace('~/dni$~','/'.$validated['dni'],rtrim($url,'/'));
   $response=Http::acceptJson()->asJson()->withToken($token)->timeout(25)->get($endpoint);
   if($response->failed()) {
    $providerMessage=$response->json('message');
    return response()->json(['message'=>$response->status()===404?'No se encontró una persona con ese DNI.':($providerMessage?:'RENIEC no pudo completar la consulta.')],$response->status()===404?404:502);
   }
   $payload=$response->json(); $data=data_get($payload,'data',data_get($payload,'result',$payload));
   $names=$this->first($data,['nombres','nombre','names','first_name']);
   $paternal=$this->first($data,['apellido_paterno','apellidoPaterno','ap_paterno','first_last_name']);
   $maternal=$this->first($data,['apellido_materno','apellidoMaterno','ap_materno','second_last_name']);
   if(!$paternal && !$maternal) {
    $combined=$this->first($data,['apellidos']);
    if($combined) { $parts=preg_split('/\s+/',trim($combined)); $paternal=$parts[0]??null; $maternal=count($parts)>1?implode(' ',array_slice($parts,1)):null; }
   }
   $full=$this->first($data,['nombre_completo','nombreCompleto','full_name','razon_social']);
   if(!$full) { $lastNames=trim(implode(' ',array_filter([$paternal,$maternal])))?:($this->first($data,['apellidos'])??''); $full=trim($names.' '.$lastNames); }
   if(!$full) return response()->json(['message'=>'RENIEC respondió, pero no devolvió el nombre completo.'],502);
   $direccion=$this->first($data,['domicilio.direccion','direccion','address']);
   $distrito=$this->first($data,['domicilio.distrito','distrito','district']);
   $provincia=$this->first($data,['domicilio.provincia','provincia','province']);
   $departamento=$this->first($data,['domicilio.departamento','departamento','department']);
   $direccionCompleta=$direccion?trim(implode(', ',array_filter([$direccion,$distrito,$provincia&&$provincia!==$distrito?$provincia:null]))):null;
   $fechaNacimientoRaw=$this->first($data,['nacimiento.fecha','fecha_nacimiento','fechaNacimiento','birth_date']);
   $fechaNacimiento=null;
   if($fechaNacimientoRaw){ try { $fechaNacimiento=Carbon::createFromFormat('d/m/Y',$fechaNacimientoRaw)->format('Y-m-d'); } catch(Throwable){ $fechaNacimiento=null; } }
   $generoRaw=$this->first($data,['genero','sexo','gender']);
   $sexo=match(mb_strtoupper($generoRaw??'')){'MASCULINO','M'=>'M','FEMENINO','F'=>'F',default=>null};
   return response()->json([
    'dni'=>$validated['dni'],
    'nombre_completo'=>mb_convert_case($full,MB_CASE_TITLE,'UTF-8'),
    'nombres'=>$names?mb_convert_case($names,MB_CASE_TITLE,'UTF-8'):null,
    'apellido_paterno'=>$paternal?mb_convert_case($paternal,MB_CASE_TITLE,'UTF-8'):null,
    'apellido_materno'=>$maternal?mb_convert_case($maternal,MB_CASE_TITLE,'UTF-8'):null,
    'direccion'=>$direccionCompleta?mb_convert_case($direccionCompleta,MB_CASE_TITLE,'UTF-8'):null,
    'distrito'=>$distrito?mb_convert_case($distrito,MB_CASE_TITLE,'UTF-8'):null,
    'provincia'=>$provincia?mb_convert_case($provincia,MB_CASE_TITLE,'UTF-8'):null,
    'departamento'=>$departamento?mb_convert_case($departamento,MB_CASE_TITLE,'UTF-8'):null,
    'fecha_nacimiento'=>$fechaNacimiento,
    'sexo'=>$sexo,
   ]);
  } catch(Throwable $e){ report($e); return response()->json(['message'=>'No fue posible conectar con RENIEC. Intenta nuevamente.'],502); }
 }
 private function first(array $data,array $keys):?string { foreach($keys as $key){$value=data_get($data,$key);if(is_string($value)&&trim($value)!==''&&!str_contains(mb_strtolower($value),'data in credit'))return trim($value);}return null; }
}
