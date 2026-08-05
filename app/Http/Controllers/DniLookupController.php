<?php
namespace App\Http\Controllers;
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
   $full=$this->first($data,['nombre_completo','nombreCompleto','full_name','razon_social']);
   if(!$full) $full=trim(implode(' ',array_filter([$this->first($data,['nombres','nombre','names','first_name']),$this->first($data,['apellidos']),$this->first($data,['apellido_paterno','apellidoPaterno','ap_paterno','first_last_name']),$this->first($data,['apellido_materno','apellidoMaterno','ap_materno','second_last_name'])])));
   if(!$full) return response()->json(['message'=>'RENIEC respondió, pero no devolvió el nombre completo.'],502);
   return response()->json(['dni'=>$validated['dni'],'nombre_completo'=>mb_convert_case($full,MB_CASE_TITLE,'UTF-8')]);
  } catch(Throwable $e){ report($e); return response()->json(['message'=>'No fue posible conectar con RENIEC. Intenta nuevamente.'],502); }
 }
 private function first(array $data,array $keys):?string { foreach($keys as $key){$value=data_get($data,$key);if(is_string($value)&&trim($value)!=='')return trim($value);}return null; }
}
