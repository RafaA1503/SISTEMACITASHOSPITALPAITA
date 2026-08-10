<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up():void {
  $systemId=DB::table('sistemas')->where('nombre','ControlAccesoPacientes')->value('idSistema'); if(!$systemId)return;
  $pages=['control_acceso'=>['Control de acceso de pacientes','Consulta y confirmación de asistencia'], 'administracion'=>['Administración','Usuarios, roles y reportes']];
  foreach($pages as $key=>[$name,$description]) DB::table('paginas')->updateOrInsert(['idSistema'=>$systemId,'descripcion'=>$key],['nombre'=>$name,'descripcion'=>$key,'tipoPagina'=>1,'estado'=>1,'idSistema'=>$systemId]);
  $actions=['control_acceso'=>[['buscar_paciente','Buscar pacientes por DNI'],['confirmar_asistencia','Confirmar asistencia e ingreso'],['registrar_movimiento','Registrar ingreso y salida']], 'administracion'=>[['gestionar_usuarios','Crear, editar, dar de baja y reactivar usuarios'],['gestionar_roles','Crear, editar y eliminar roles'],['ver_reportes','Consultar reportes']]];
  foreach($actions as $pageKey=>$list){$pageId=DB::table('paginas')->where('idSistema',$systemId)->where('descripcion',$pageKey)->value('idPagina'); foreach($list as [$name,$description])DB::table('acciones')->updateOrInsert(['idPagina'=>$pageId,'nombre_Accion'=>$name],['idPagina'=>$pageId,'nombre_Accion'=>$name,'descripcion'=>$description,'estado'=>1]);}
  $admin=DB::table('roles')->where('idSistema',$systemId)->where('nombreRol','administrador')->value('idRol'); $portero=DB::table('roles')->where('idSistema',$systemId)->where('nombreRol','portero')->value('idRol');
  foreach([[$admin,['control_acceso','administracion']],[$portero,['control_acceso']]] as [$roleId,$pageKeys])if($roleId)foreach($pageKeys as $pageKey){$pageId=DB::table('paginas')->where('idSistema',$systemId)->where('descripcion',$pageKey)->value('idPagina'); DB::table('accesos')->updateOrInsert(['idRol'=>$roleId,'idPagina'=>$pageId],['Estado'=>1]); foreach(DB::table('acciones')->where('idPagina',$pageId)->pluck('idAccion') as $actionId) DB::table('accesosacciones')->updateOrInsert(['idRol'=>$roleId,'idAccion'=>$actionId],['Estado'=>1]);}
 }
 public function down():void{}
};
