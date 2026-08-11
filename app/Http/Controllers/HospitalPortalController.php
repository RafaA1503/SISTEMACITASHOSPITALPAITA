<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HospitalPortalController extends Controller
{
    private const MODULES = ['portero','administrador'];
    private const ATTENDANCE_GRACE_MINUTES = 20;
    /**
     * Una cita es visible tanto en Portería (siempre, dentro de su rango de
     * fecha) como en Atención profesional (solo si su estado ya es visible
     * ahí). Al cambiar de estado puede "aparecer" o "desaparecer" de la vista
     * profesional, así que cada contenedor recibe su propia acción.
     */
    private function broadcastAppointment(Request $request, Appointment $appointment, string $porteriaAction, ?string $fromStatus = null)
    {
        $appointment->loadMissing(['patient', 'type.service', 'area', 'subarea']);
        $targets = [[
            'selector' => '#patientsTableBody',
            'action' => $porteriaAction,
            'id' => $appointment->id,
            'html' => $porteriaAction === 'deleted' ? null : view('portal.partials.patient-row', ['a' => $appointment])->render(),
        ]];

        return LiveUpdate::respondMulti($request, 'citas', $targets);
    }

    public function index(Request $request, ?string $role=null): View
    {
        $user=$request->user();
        $role ??= $user->defaultModule();
        abort_unless(in_array($role,self::MODULES,true),404);
        abort_unless($user->canAccessModule($role),403,'No tiene permiso para acceder a este módulo.');

        $dateFrom=$role==='portero'&&$request->date('from')?$request->date('from'):today();
        $dateTo=$role==='portero'&&$request->date('to')?$request->date('to'):today();
        if($dateTo->lessThan($dateFrom)) $dateTo=$dateFrom->copy();

        $query=Appointment::with(['patient','type.service','area','subarea'])->orderBy('scheduled_at');
        if($role==='portero') {
            $query->whereBetween('scheduled_at',[$dateFrom->copy()->startOfDay(),$dateTo->copy()->endOfDay()]);
        } else {
            $query->whereDate('scheduled_at',today());
        }
        $today=$query->get();
        $services=Service::where('active',true)->with(['appointmentTypes'=>fn($q)=>$q->where('active',true)])->orderBy('name')->get();
        $metrics=['citas_hoy'=>Appointment::whereDate('scheduled_at',today())->count(),'confirmadas'=>Appointment::whereDate('scheduled_at',today())->where('status','confirmada')->count(),'ingresos'=>DB::table('access_logs')->whereDate('registered_at',today())->where('movement','ingreso')->count(),'pendientes'=>Appointment::whereDate('scheduled_at',today())->where('status','programada')->count()];
        $upcoming=$role==='portero'?Appointment::with(['patient','type.service'])->whereDate('scheduled_at',today())->where('status','programada')->whereBetween('scheduled_at',[now(),now()->addMinutes(60)])->orderBy('scheduled_at')->get():collect();
        return view('portal',compact('role','today','metrics','services','user','upcoming','dateFrom','dateTo'));
    }

    /** Profesionales con un servicio ya asignado (vía trabajador) — para el selector de citas. */
    private function professionalUsers()
    {
        return User::withRoles(['profesional','laboratorio','imagenes'])->filter(fn($u)=>$u->service_id!==null)->values();
    }

    public function storeAppointment(Request $request)
    {
        abort_unless($request->user()->canAccessModule('citas'),403);
        $data=$request->validate(['dni'=>'required|digits:8','patient_name'=>'required|string|max:200','first_names'=>'nullable|string|max:120','paternal_surname'=>'nullable|string|max:60','maternal_surname'=>'nullable|string|max:60','birth_date'=>'nullable|date|before_or_equal:today','sex'=>'nullable|in:M,F,O','phone'=>'nullable|string|max:20','address'=>'nullable|string|max:180','email'=>'nullable|email|max:120','medical_record_number'=>'nullable|string|max:30','insurance'=>'nullable|string|max:100','appointment_type_id'=>'required|exists:appointment_types,id','service_area_id'=>'nullable|exists:service_areas,id','service_subarea_id'=>'nullable|exists:service_subareas,id','professional_id'=>'nullable|exists:users,id','scheduled_at'=>'required|date','room'=>'nullable|string|max:100','notes'=>'nullable|string|max:500']);
        $type=AppointmentType::findOrFail($data['appointment_type_id']);
        if(!empty($data['service_area_id']) && !ServiceArea::whereKey($data['service_area_id'])->where('service_id',$type->service_id)->exists()) {
            return back()->withInput()->withErrors('El área no pertenece al servicio seleccionado.');
        }
        $professional=null;
        $trabajadorId=null;
        if(!empty($data['professional_id'])) {
            $professional=User::find($data['professional_id']);
            if(!$professional || !$professional->active) {
                return back()->withInput()->withErrors('El profesional no está activo.');
            }
            $trabajadorId=$professional->trabajadorRecord?->idTrabajador;
            $scheduledAt = \Illuminate\Support\Carbon::parse($data['scheduled_at']);
            $isScheduled = ProfessionalSchedule::where('trabajador_id', $trabajadorId)
                ->where('service_id', $type->service_id)
                ->whereDate('scheduled_date', $scheduledAt->toDateString())
                ->where('active', true)
                ->where(fn ($query) => $query->whereNull('service_area_id')->orWhere('service_area_id', $data['service_area_id'] ?? null))
                ->whereHas('shift', fn ($query) => $query->where('starts_at', '<=', $scheduledAt->format('H:i:s'))->where('ends_at', '>=', $scheduledAt->format('H:i:s')))
                ->exists();
            if (! $isScheduled) {
                return back()->withInput()->withErrors('El profesional no tiene un turno vigente para ese servicio, área y hora. Verifica su programación en "Servicios y profesionales".');
            }
        }
        $identity=$this->patientIdentity($data);
        $patient=Patient::firstOrNew(['NroDocumento'=>$data['dni']]);
        $patient->fill(array_filter(array_merge($identity,['birth_date'=>$data['birth_date']??null,'sex'=>$data['sex']??null,'phone'=>$data['phone']??null,'address'=>$data['address']??null,'email'=>$data['email']??null,'medical_record_number'=>$data['medical_record_number']??null,'insurance'=>$data['insurance']??null]),fn($value)=>$value!==null&&$value!==''));
        $patient->save();
        $appointment=Appointment::create(['code'=>'CIT-'.now()->format('ymdHis').'-'.random_int(10,99),'paciente_id'=>$patient->IdPaciente,'appointment_type_id'=>$data['appointment_type_id'],'service_area_id'=>$data['service_area_id']??null,'service_subarea_id'=>$data['service_subarea_id']??null,'trabajador_id'=>$trabajadorId,'created_by'=>$request->user()->id,'scheduled_at'=>$data['scheduled_at'],'status'=>'programada','room'=>$data['room']??null,'notes'=>$data['notes']??null]);
        $this->recordStatus($request,$appointment,null,'programada','Cita registrada por Admisión.');

        if ($response = $this->broadcastAppointment($request, $appointment, 'created')) {
            return $response;
        }

        return back()->with('success','Cita registrada y enviada al servicio correspondiente.');
    }

    private function patientIdentity(array $data):array
    {
        if(!empty($data['first_names'])) return ['first_names'=>$data['first_names'],'paternal_surname'=>$data['paternal_surname']??null,'maternal_surname'=>$data['maternal_surname']??null];
        $parts=preg_split('/\s+/',trim($data['patient_name']));
        if(count($parts)>=3){$maternal=array_pop($parts);$paternal=array_pop($parts);return ['first_names'=>implode(' ',$parts),'paternal_surname'=>$paternal,'maternal_surname'=>$maternal];}
        return ['first_names'=>$parts[0]??$data['patient_name'],'paternal_surname'=>$parts[1]??null];
    }

    public function confirmAppointment(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        $data=$request->validate(['access_point'=>'nullable|string|max:80']);
        if (! in_array($appointment->status, ['programada', 'no_asistio'], true)) return back()->withErrors('La cita ya fue confirmada o no está disponible.');
        $from = $appointment->status;
        DB::transaction(function()use($request,$appointment,$data){
            $from=$appointment->status; $point=$data['access_point']??'Puerta principal';
            $appointment->update(['status'=>'confirmada','confirmed_by'=>$request->user()->id,'confirmed_at'=>now(),'access_point'=>$point]);
            DB::table('access_logs')->insert(['paciente_id'=>$appointment->paciente_id,'appointment_id'=>$appointment->id,'registered_by'=>$request->user()->id,'movement'=>'ingreso','person_type'=>'paciente','access_point'=>$point,'reason'=>'Asistencia a '.$appointment->type()->value('name'),'companions'=>0,'notes'=>'Ingreso generado al confirmar la cita.','registered_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            $this->recordStatus($request,$appointment,$from,'confirmada','Llegada confirmada por Portería.');
        });

        if ($response = $this->broadcastAppointment($request, $appointment, 'updated', $from)) {
            return $response;
        }

        return back()->with('success','Asistencia e ingreso confirmados. El paciente ya aparece en el panel profesional.');
    }

    public function markNoShow(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        if ($appointment->status !== 'programada') return back()->withErrors('Solo se puede marcar inasistencia en citas que aún no fueron confirmadas.');
        $from = $appointment->status;
        DB::transaction(function()use($request,$appointment){
            $from=$appointment->status;
            $appointment->update(['status'=>'no_asistio']);
            $this->recordStatus($request,$appointment,$from,'no_asistio','Paciente marcado como inasistencia por Portería.');
        });

        if ($response = $this->broadcastAppointment($request, $appointment, 'updated', $from)) {
            return $response;
        }

        return back()->with('success','Se registró la inasistencia del paciente.');
    }

    public function pendingCount(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        $count=Appointment::whereDate('scheduled_at',today())->where('status','programada')->where('scheduled_at','<=',now()->addMinutes(60))->count();
        return response()->json(['count'=>$count]);
    }

    public function completeAppointment(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->canAccessModule('servicio'),403);
        $ownTrabajadorId=$request->user()->trabajadorRecord?->idTrabajador;
        if($request->user()->role!=='administrador') abort_unless($appointment->trabajador_id===$ownTrabajadorId,403);
        if (! in_array($appointment->status,['confirmada','ingreso'],true)) return back()->withErrors('Portería debe confirmar primero la llegada del paciente.');
        $from = $appointment->status;
        DB::transaction(function()use($request,$appointment,$ownTrabajadorId){$from=$appointment->status;$appointment->update(['status'=>'atendida','trabajador_id'=>$appointment->trabajador_id ?: $ownTrabajadorId,'attention_started_at'=>$appointment->attention_started_at?:now(),'attention_completed_at'=>now()]);$this->recordStatus($request,$appointment,$from,'atendida','Atención confirmada por el profesional.');});

        if ($response = $this->broadcastAppointment($request, $appointment, 'updated', $from)) {
            return $response;
        }

        return back()->with('success','Atención del paciente confirmada correctamente.');
    }

    private function recordStatus(Request $request,Appointment $appointment,?string $from,string $to,?string $notes=null):void
    {
        DB::table('appointment_status_history')->insert(['appointment_id'=>$appointment->id,'changed_by'=>$request->user()->id,'from_status'=>$from,'to_status'=>$to,'notes'=>$notes,'ip_address'=>$request->ip(),'created_at'=>now()]);
    }

    public function patient(Request $request, string $dni):JsonResponse
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        $patient=Patient::where('NroDocumento',$dni)->first(); if(!$patient)return response()->json(['message'=>'Paciente no encontrado.'],404);
        $appointments=$patient->appointments()->with('type.service')->whereDate('scheduled_at','>=',today())->orderBy('scheduled_at')->get()->map(fn($a)=>['code'=>$a->code,'date'=>$a->scheduled_at->format('d/m/Y'),'time'=>$a->scheduled_at->format('H:i'),'type'=>$a->type->name,'service'=>$a->type->service->name,'location'=>$a->room?:$a->type->service->location,'status'=>$a->status,'preparation'=>$a->type->preparation]);
        return response()->json(['patient'=>['dni'=>$patient->dni,'name'=>$patient->full_name,'insurance'=>$patient->insurance],'appointments'=>$appointments]);
    }

    public function registerAccess(Request $request):JsonResponse
    {
        abort_unless($request->user()->canAccessModule('portero'),403);
        $data=$request->validate(['dni'=>'required|digits:8','movement'=>'required|in:ingreso,salida','appointment_id'=>'nullable|exists:citas_control_acceso,id','access_point'=>'required|string|max:80']); $patient=Patient::where('NroDocumento',$data['dni'])->firstOrFail();
        DB::table('access_logs')->insert(['paciente_id'=>$patient->IdPaciente,'appointment_id'=>$data['appointment_id']??null,'registered_by'=>$request->user()->id,'movement'=>$data['movement'],'person_type'=>'paciente','access_point'=>$data['access_point'],'reason'=>'Cita hospitalaria','registered_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Movimiento registrado correctamente.']);
    }
}
