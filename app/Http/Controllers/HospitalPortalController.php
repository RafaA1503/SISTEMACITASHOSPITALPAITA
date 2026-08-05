<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServiceArea;
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

        $query=Appointment::with(['patient','type.service','professional','area','subarea'])->whereDate('scheduled_at',today())->orderBy('scheduled_at');
        if($role==='servicio') {
            $query->whereIn('status',['confirmada','ingreso','atendida']);
            if($user->role!=='administrador') $query->whereHas('type',fn($q)=>$q->where('service_id',$user->service_id));
        }
        $today=$query->get();
        $services=Service::where('active',true)->with(['appointmentTypes'=>fn($q)=>$q->where('active',true)])->orderBy('name')->get();
        $areas=ServiceArea::where('active',true)->with(['service','subareas'=>fn($q)=>$q->where('active',true)->orderBy('name')])->orderBy('name')->get();
        $professionals=User::where('active',true)->whereNotNull('service_id')->orderBy('name')->get();
        $metrics=['citas_hoy'=>Appointment::whereDate('scheduled_at',today())->count(),'confirmadas'=>Appointment::whereDate('scheduled_at',today())->where('status','confirmada')->count(),'ingresos'=>DB::table('access_logs')->whereDate('registered_at',today())->where('movement','ingreso')->count(),'pendientes'=>Appointment::whereDate('scheduled_at',today())->where('status','programada')->count()];
        return view('portal',compact('role','today','metrics','services','areas','professionals','user'));
    }

    public function storeAppointment(Request $request)
    {
        abort_unless($request->user()->canAccessModule('citas'),403);
        $data=$request->validate(['dni'=>'required|digits:8','patient_name'=>'required|string|max:200','first_names'=>'nullable|string|max:120','paternal_surname'=>'nullable|string|max:60','maternal_surname'=>'nullable|string|max:60','birth_date'=>'nullable|date|before_or_equal:today','sex'=>'nullable|in:M,F,O','phone'=>'nullable|string|max:20','address'=>'nullable|string|max:180','email'=>'nullable|email|max:120','medical_record_number'=>'nullable|string|max:30','insurance'=>'nullable|string|max:100','appointment_type_id'=>'required|exists:appointment_types,id','service_area_id'=>'nullable|exists:service_areas,id','service_subarea_id'=>'nullable|exists:service_subareas,id','professional_id'=>'nullable|exists:users,id','scheduled_at'=>'required|date','room'=>'nullable|string|max:100','notes'=>'nullable|string|max:500']);
        $type=AppointmentType::findOrFail($data['appointment_type_id']);
        if(!empty($data['service_area_id'])) abort_unless(ServiceArea::whereKey($data['service_area_id'])->where('service_id',$type->service_id)->exists(),422,'El área no pertenece al servicio seleccionado.');
        if(!empty($data['professional_id'])) abort_unless(User::whereKey($data['professional_id'])->where('service_id',$type->service_id)->exists(),422,'El profesional no pertenece al servicio seleccionado.');
        $identity=$this->patientIdentity($data);
        $patient=Patient::firstOrNew(['dni'=>$data['dni']]);
        $patient->fill(array_filter(array_merge($identity,['document_type'=>'DNI','birth_date'=>$data['birth_date']??null,'sex'=>$data['sex']??null,'phone'=>$data['phone']??null,'address'=>$data['address']??null,'email'=>$data['email']??null,'medical_record_number'=>$data['medical_record_number']??null,'insurance'=>$data['insurance']??null]),fn($value)=>$value!==null&&$value!==''));
        $patient->save();
        $appointment=Appointment::create(['code'=>'CIT-'.now()->format('ymdHis').'-'.random_int(10,99),'patient_id'=>$patient->id,'appointment_type_id'=>$data['appointment_type_id'],'service_area_id'=>$data['service_area_id']??null,'service_subarea_id'=>$data['service_subarea_id']??null,'professional_id'=>$data['professional_id']??null,'created_by'=>$request->user()->id,'scheduled_at'=>$data['scheduled_at'],'status'=>'programada','room'=>$data['room']??null,'notes'=>$data['notes']??null]);
        $this->recordStatus($request,$appointment,null,'programada','Cita registrada por Admisión.');
        return back()->with('success','Cita registrada y enviada al servicio correspondiente.');
    }

    private function patientIdentity(array $data):array
    {
        if(!empty($data['first_names'])) return ['first_names'=>$data['first_names'],'last_names'=>trim(($data['paternal_surname']??'').' '.($data['maternal_surname']??'')),'paternal_surname'=>$data['paternal_surname']??null,'maternal_surname'=>$data['maternal_surname']??null];
        $parts=preg_split('/\s+/',trim($data['patient_name']));
        if(count($parts)>=3){$maternal=array_pop($parts);$paternal=array_pop($parts);return ['first_names'=>implode(' ',$parts),'last_names'=>"$paternal $maternal",'paternal_surname'=>$paternal,'maternal_surname'=>$maternal];}
        return ['first_names'=>$parts[0]??$data['patient_name'],'last_names'=>$parts[1]??''];
    }

    public function confirmAppointment(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        $data=$request->validate(['access_point'=>'nullable|string|max:80']);
        abort_unless($appointment->status==='programada',422,'La cita ya fue confirmada o no está disponible.');
        DB::transaction(function()use($request,$appointment,$data){
            $from=$appointment->status; $point=$data['access_point']??'Puerta principal';
            $appointment->update(['status'=>'confirmada','confirmed_by'=>$request->user()->id,'confirmed_at'=>now(),'access_point'=>$point]);
            DB::table('access_logs')->insert(['patient_id'=>$appointment->patient_id,'appointment_id'=>$appointment->id,'registered_by'=>$request->user()->id,'movement'=>'ingreso','person_type'=>'paciente','access_point'=>$point,'reason'=>'Asistencia a '.$appointment->type()->value('name'),'companions'=>0,'notes'=>'Ingreso generado al confirmar la cita.','registered_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            $this->recordStatus($request,$appointment,$from,'confirmada','Llegada confirmada por Portería.');
        });
        return back()->with('success','Asistencia e ingreso confirmados. El paciente ya aparece en el panel profesional.');
    }

    public function completeAppointment(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->canAccessModule('servicio'),403);
        if($request->user()->role!=='administrador') abort_unless($appointment->type()->where('service_id',$request->user()->service_id)->exists(),403);
        abort_unless(in_array($appointment->status,['confirmada','ingreso'],true),422,'Portería debe confirmar primero la llegada del paciente.');
        DB::transaction(function()use($request,$appointment){$from=$appointment->status;$appointment->update(['status'=>'atendida','professional_id'=>$appointment->professional_id ?: $request->user()->id,'attention_started_at'=>$appointment->attention_started_at?:now(),'attention_completed_at'=>now()]);$this->recordStatus($request,$appointment,$from,'atendida','Atención confirmada por el profesional.');});
        return back()->with('success','Atención del paciente confirmada correctamente.');
    }

    private function recordStatus(Request $request,Appointment $appointment,?string $from,string $to,?string $notes=null):void
    {
        DB::table('appointment_status_history')->insert(['appointment_id'=>$appointment->id,'changed_by'=>$request->user()->id,'from_status'=>$from,'to_status'=>$to,'notes'=>$notes,'ip_address'=>$request->ip(),'created_at'=>now()]);
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
