<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
class DatabaseSeeder extends Seeder {
 public function run():void {
  $now=now();
  $services=[['LAB','Laboratorio Clínico','laboratorio','Piso 2 · Laboratorio'],['IMG','Diagnóstico por Imágenes','imagenes','Piso 1 · Imagenología'],['MED','Medicina General','consulta','Consultorios externos'],['PED','Pediatría','consulta','Piso 1 · Consultorio 105'],['GIN','Ginecología','consulta','Piso 1 · Consultorio 110'],['ADM','Admisión','administrativo','Hall principal']];
  foreach($services as [$code,$name,$category,$location]) DB::table('services')->insert(['code'=>$code,'name'=>$name,'category'=>$category,'location'=>$location,'active'=>1,'created_at'=>$now,'updated_at'=>$now]);
  $sid=DB::table('services')->pluck('id','code');
  $users=[['Administrador','admin@lamerced.gob.pe','administrador','ADM'],['Carlos Mendoza','porteria@lamerced.gob.pe','portero','ADM'],['Rosa Castillo','admision@lamerced.gob.pe','admision','ADM'],['Dra. Elena Ramos','medicina@lamerced.gob.pe','profesional','MED'],['Lic. Juan Torres','laboratorio@lamerced.gob.pe','laboratorio','LAB'],['Dr. Miguel Silva','imagenes@lamerced.gob.pe','imagenes','IMG']];
  foreach($users as $i=>[$name,$email,$role,$service]) DB::table('users')->insert(['name'=>$name,'email'=>$email,'password'=>Hash::make('Hospital2026!'),'role'=>$role,'service_id'=>$sid[$service],'document_number'=>(string)(40000001+$i),'active'=>1,'created_at'=>$now,'updated_at'=>$now]);
  $uid=DB::table('users')->pluck('id','role');
  $types=[['HEM','Hemograma completo','LAB','Sangre venosa · Ayuno según indicación'],['GLU','Glucosa en sangre','LAB','Sangre venosa · Ayuno de 8 horas'],['ORI','Examen completo de orina','LAB','Primera orina de la mañana'],['BIO','Perfil bioquímico','LAB','Sangre venosa · Ayuno de 10 horas'],['RX','Radiografía','IMG','Retirar objetos metálicos'],['TAC','Tomografía','IMG','Presentar creatinina si usa contraste'],['ECO','Ecografía','IMG','Preparación según zona a examinar'],['RM','Resonancia magnética','IMG','Informar implantes o marcapasos'],['CG','Consulta medicina general','MED',null],['CP','Consulta pediátrica','PED',null],['CGIN','Consulta ginecológica','GIN',null]];
  foreach($types as [$code,$name,$service,$prep]) DB::table('appointment_types')->insert(['service_id'=>$sid[$service],'name'=>$name,'code'=>$code,'duration_minutes'=>in_array($code,['TAC','RM'])?40:20,'preparation'=>$prep,'requires_order'=>!str_starts_with($code,'C'),'active'=>1,'created_at'=>$now,'updated_at'=>$now]);
  $tid=DB::table('appointment_types')->pluck('id','code');
  $patients=[['71524353','Jesús Rafael','Arévalo Aguirre','1992-04-18','M'],['70452918','María Elena','López Torres','1988-11-02','F'],['43982617','Jorge Luis','Salazar Peña','1975-06-15','M'],['45872931','Ana Patricia','Ruiz Mendoza','1991-09-23','F'],['61230984','Luis Alberto','Paredes Silva','1983-01-10','M'],['38741925','Carmen Rosa','Díaz Rojas','1969-12-06','F']];
  foreach($patients as [$dni,$names,$last,$birth,$sex]) DB::table('patients')->insert(['dni'=>$dni,'first_names'=>$names,'last_names'=>$last,'birth_date'=>$birth,'sex'=>$sex,'insurance'=>'SIS','phone'=>'900000000','created_at'=>$now,'updated_at'=>$now]);
  $pid=DB::table('patients')->pluck('id','dni');
  $appointments=[['08:30','70452918','HEM','ingreso','LAB-1042'],['09:15','43982617','RM','confirmada','RM-0837'],['09:40','45872931','TAC','programada','TAC-2841'],['10:00','61230984','ECO','programada','ECO-3348'],['10:30','38741925','RX','programada','RX-2195'],['11:15','71524353','ORI','programada','ORI-9921']];
  foreach($appointments as [$time,$dni,$type,$status,$code]) DB::table('appointments')->insert(['code'=>$code,'patient_id'=>$pid[$dni],'appointment_type_id'=>$tid[$type],'professional_id'=>in_array($type,['HEM','ORI','GLU','BIO'])?$uid['laboratorio']:$uid['imagenes'],'created_by'=>$uid['admision'],'scheduled_at'=>Carbon::today()->setTimeFromTimeString($time),'status'=>$status,'room'=>in_array($type,['HEM','ORI'])?'Lab. 204':'Sala de imágenes','created_at'=>$now,'updated_at'=>$now]);
  DB::table('medical_orders')->insert(['code'=>'ORD-2026-001','patient_id'=>$pid['71524353'],'ordered_by'=>$uid['profesional'],'service_id'=>$sid['LAB'],'ordered_at'=>today(),'diagnosis'=>'Control preventivo','priority'=>'normal','status'=>'agendada','created_at'=>$now,'updated_at'=>$now]);
  $order=DB::getPdo()->lastInsertId(); DB::table('order_items')->insert(['medical_order_id'=>$order,'appointment_type_id'=>$tid['ORI'],'appointment_id'=>DB::table('appointments')->where('code','ORI-9921')->value('id'),'specimen'=>'Orina','clinical_indication'=>'Control anual','status'=>'pendiente','created_at'=>$now,'updated_at'=>$now]);
  DB::table('referrals')->insert(['code'=>'IC-2026-041','patient_id'=>$pid['45872931'],'requested_by'=>$uid['profesional'],'from_service_id'=>$sid['MED'],'to_service_id'=>$sid['GIN'],'reason'=>'Evaluación especializada','priority'=>'preferente','status'=>'solicitada','created_at'=>$now,'updated_at'=>$now]);
 }
}
