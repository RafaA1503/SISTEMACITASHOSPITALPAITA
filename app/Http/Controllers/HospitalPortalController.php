<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HospitalPortalController extends Controller
{
    private const MODULES = ['portero','citas','servicio','administrador'];

    public function index(Request $request, ?string $role=null): View
    {
        $user=$request->user()->load('customRole','service');
        $role ??= match($user->role){'portero'=>'portero','admision'=>'citas','administrador'=>'administrador',default=>'servicio'};
        abort_unless(in_array($role,self::MODULES,true),404);
        abort_unless($user->canAccessModule($role),403,'No tiene permiso para acceder a este módulo.');

        $query=Appointment::with(['patient','type.service','professional'])->whereDate('scheduled_at',today())->orderBy('scheduled_at');
        if($role==='servicio' && $user->role!=='administrador') $query->whereHas('type',fn($q)=>$q->where('service_id',$user->service_id));
        $today=$query->get();
        $services=Service::where('active',true)->with(['appointmentTypes'=>fn($q)=>$q->where('active',true)])->orderBy('name')->get();
        $professionals=User::where('active',true)->whereNotNull('service_id')->orderBy('name')->get();
        $metrics=['citas_hoy'=>Appointment::whereDate('scheduled_at',today())->count(),'confirmadas'=>Appointment::whereDate('scheduled_at',today())->where('status','confirmada')->count(),'ingresos'=>Appointment::whereDate('scheduled_at',today())->where('status','ingreso')->count(),'pendientes'=>Appointment::whereDate('scheduled_at',today())->where('status','programada')->count()];
        return view('portal',compact('role','today','metrics','services','professionals','user'));
    }

    public function storeAppointment(Request $request)
    {
        abort_unless($request->user()->canAccessModule('citas'),403);
        $data=$request->validate(['dni'=>'required|digits:8','patient_name'=>'required|string|max:160','appointment_type_id'=>'required|exists:appointment_types,id','professional_id'=>'nullable|exists:users,id','scheduled_at'=>'required|date','room'=>'nullable|string|max:100','notes'=>'nullable|string|max:500']);
        $parts=preg_split('/\s+/',trim($data['patient_name']),2);
        $patient=Patient::updateOrCreate(['dni'=>$data['dni']],['first_names'=>$parts[0],'last_names'=>$parts[1]??'']);
        Appointment::create(['code'=>'CIT-'.now()->format('ymdHis').'-'.random_int(10,99),'patient_id'=>$patient->id,'appointment_type_id'=>$data['appointment_type_id'],'professional_id'=>$data['professional_id']??null,'created_by'=>$request->user()->id,'scheduled_at'=>$data['scheduled_at'],'status'=>'programada','room'=>$data['room']??null,'notes'=>$data['notes']??null]);
        return back()->with('success','Cita registrada y enviada al servicio correspondiente.');
    }

    public function confirmAppointment(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        $appointment->update(['status'=>'confirmada']);
        return back()->with('success','Asistencia confirmada por portería.');
    }

    public function completeAppointment(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->canAccessModule('servicio'),403);
        if($request->user()->role!=='administrador') abort_unless($appointment->type()->where('service_id',$request->user()->service_id)->exists(),403);
        abort_unless(in_array($appointment->status,['confirmada','ingreso'],true),422,'Portería debe confirmar primero la llegada del paciente.');
        $appointment->update(['status'=>'atendida','professional_id'=>$appointment->professional_id ?: $request->user()->id]);
        return back()->with('success','Atención del paciente confirmada correctamente.');
    }

    public function patient(string $dni):JsonResponse
    {
        $patient=Patient::where('dni',$dni)->first(); if(!$patient)return response()->json(['message'=>'Paciente no encontrado.'],404);
        $appointments=$patient->appointments()->with('type.service')->whereDate('scheduled_at','>=',today())->orderBy('scheduled_at')->get()->map(fn($a)=>['code'=>$a->code,'date'=>$a->scheduled_at->format('d/m/Y'),'time'=>$a->scheduled_at->format('H:i'),'type'=>$a->type->name,'service'=>$a->type->service->name,'location'=>$a->room?:$a->type->service->location,'status'=>$a->status,'preparation'=>$a->type->preparation]);
        return response()->json(['patient'=>['dni'=>$patient->dni,'name'=>$patient->full_name,'insurance'=>$patient->insurance],'appointments'=>$appointments]);
    }

    public function registerAccess(Request $request):JsonResponse
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        $data=$request->validate(['dni'=>'required|digits:8','movement'=>'required|in:ingreso,salida','appointment_id'=>'nullable|exists:appointments,id','access_point'=>'required|string|max:80']); $patient=Patient::where('dni',$data['dni'])->firstOrFail();
        DB::table('access_logs')->insert(['patient_id'=>$patient->id,'appointment_id'=>$data['appointment_id']??null,'registered_by'=>$request->user()->id,'movement'=>$data['movement'],'person_type'=>'paciente','access_point'=>$data['access_point'],'reason'=>'Cita hospitalaria','registered_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Movimiento registrado correctamente.']);
    }
}
