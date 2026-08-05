<?php
namespace App\Http\Controllers;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class HospitalPortalController extends Controller {
 private const ROLES=['portero','admision','profesional','laboratorio','imagenes','administrador'];
 public function index(?string $role=null):View {
  $role ??= auth()->user()->role;
  abort_unless(in_array($role,self::ROLES,true),404);
  if(auth()->user()->role!=='administrador' && auth()->user()->role!==$role) abort(403,'No tiene permiso para acceder a este módulo.');
  $today=Appointment::with(['patient','type.service','professional'])->whereDate('scheduled_at',today())->orderBy('scheduled_at')->get();
  $serviceCategory=match($role){'laboratorio'=>'laboratorio','imagenes'=>'imagenes',default=>null};
  if($serviceCategory)$today=$today->filter(fn($a)=>$a->type->service->category===$serviceCategory)->values();
  $metrics=['citas_hoy'=>Appointment::whereDate('scheduled_at',today())->count(),'laboratorio'=>Appointment::whereDate('scheduled_at',today())->whereHas('type.service',fn($q)=>$q->where('category','laboratorio'))->count(),'imagenes'=>Appointment::whereDate('scheduled_at',today())->whereHas('type.service',fn($q)=>$q->where('category','imagenes'))->count(),'interconsultas'=>DB::table('referrals')->where('status','solicitada')->count()];
  $services=Service::where('active',true)->orderBy('name')->get();
  $orders=DB::table('medical_orders')->join('patients','patients.id','=','medical_orders.patient_id')->join('services','services.id','=','medical_orders.service_id')->select('medical_orders.*','patients.first_names','patients.last_names','services.name as service_name')->latest('medical_orders.id')->limit(8)->get();
  $referrals=DB::table('referrals')->join('patients','patients.id','=','referrals.patient_id')->join('services as origin','origin.id','=','referrals.from_service_id')->join('services as destination','destination.id','=','referrals.to_service_id')->select('referrals.*','patients.first_names','patients.last_names','origin.name as origin_name','destination.name as destination_name')->latest('referrals.id')->limit(8)->get();
  return view('portal',compact('role','today','metrics','services','orders','referrals'));
 }
 public function patient(string $dni):JsonResponse {
  $patient=Patient::where('dni',$dni)->first(); if(!$patient)return response()->json(['message'=>'Paciente no encontrado.'],404);
  $appointments=$patient->appointments()->with('type.service')->whereDate('scheduled_at','>=',today())->orderBy('scheduled_at')->get()->map(fn($a)=>['code'=>$a->code,'date'=>$a->scheduled_at->format('d/m/Y'),'time'=>$a->scheduled_at->format('H:i'),'type'=>$a->type->name,'service'=>$a->type->service->name,'location'=>$a->room?:$a->type->service->location,'status'=>$a->status,'preparation'=>$a->type->preparation]);
  return response()->json(['patient'=>['dni'=>$patient->dni,'name'=>$patient->full_name,'insurance'=>$patient->insurance],'appointments'=>$appointments]);
 }
 public function registerAccess(Request $request):JsonResponse {
  $data=$request->validate(['dni'=>'required|digits:8','movement'=>'required|in:ingreso,salida','appointment_id'=>'nullable|exists:appointments,id','access_point'=>'required|string|max:80']); $patient=Patient::where('dni',$data['dni'])->firstOrFail(); $portero=DB::table('users')->where('role','portero')->value('id');
  DB::table('access_logs')->insert(['patient_id'=>$patient->id,'appointment_id'=>$data['appointment_id']??null,'registered_by'=>$portero,'movement'=>$data['movement'],'person_type'=>'paciente','access_point'=>$data['access_point'],'reason'=>'Cita hospitalaria','registered_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  return response()->json(['message'=>'Movimiento registrado correctamente.']);
 }
}
