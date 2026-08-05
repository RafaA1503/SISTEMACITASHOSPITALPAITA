<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class ConfigurePasskeyOrigin {
 public function handle(Request $request, Closure $next):Response {
  $origin=$request->getSchemeAndHttpHost();
  $allowed=array_map(fn($item)=>rtrim((string)$item,'/'),config('passkeys.allowed_origins',[]));
  $local=in_array($request->getHost(),['localhost','127.0.0.1','::1'],true);
  if($local || in_array(rtrim($origin,'/'),$allowed,true)) config(['passkeys.relying_party_id'=>$request->getHost(),'passkeys.allowed_origins'=>[$origin]]);
  return $next($request);
 }
}
