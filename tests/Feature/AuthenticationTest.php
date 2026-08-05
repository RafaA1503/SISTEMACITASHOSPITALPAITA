<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\Appointment; use App\Models\AppointmentType; use App\Models\CustomRole; use App\Models\Service;
class AuthenticationTest extends TestCase {
 use RefreshDatabase;
 public function test_administrator_can_login_and_manage_users():void {
  $this->seed();
  $admin=User::where('email','jarevaloagu2@gmail.com')->firstOrFail();
  $this->post('/login',['email'=>$admin->email,'password'=>'admin123'])->assertRedirect('/portal/administrador');
  $this->actingAs($admin)->get('/administracion/usuarios')->assertOk();
 }
 public function test_inactive_user_cannot_login():void {
  $user=User::create(['name'=>'Inactivo','email'=>'inactivo@example.com','password'=>'password123','role'=>'portero','active'=>false]);
  $this->post('/login',['email'=>$user->email,'password'=>'password123'])->assertSessionHasErrors('email');
 }
 public function test_guests_cannot_access_clinical_pages_or_apis():void {
  $this->get('/citas')->assertRedirect('/login');
  $this->get('/pacientes')->assertRedirect('/login');
  $this->get('/control-acceso')->assertRedirect('/login');
  $this->get('/api/consultar-dni/71524353')->assertRedirect('/login');
  $this->get('/api/pacientes/71524353/citas')->assertRedirect('/login');
 }
 public function test_authenticated_user_is_sent_from_home_to_own_portal():void {
  $user=User::create(['name'=>'Portero','email'=>'portero@example.com','password'=>'password123','role'=>'portero','active'=>true]);
  $this->actingAs($user)->get('/')->assertRedirect('/portal/portero');
 }
 public function test_user_can_upload_and_view_profile_photo():void {
  Storage::fake('public');
  $this->seed();
  $user=User::where('email','jarevaloagu2@gmail.com')->firstOrFail();
  $this->actingAs($user)->put('/configuracion/perfil',['name'=>$user->name,'photo'=>UploadedFile::fake()->image('perfil.jpg')])->assertSessionHas('success');
  $user->refresh(); Storage::disk('public')->assertExists($user->photo_path);
  $this->actingAs($user)->get('/configuracion/foto')->assertOk();
 }
 public function test_admission_can_register_an_appointment_for_a_service():void {
  $this->seed();
  $user=User::where('role','admision')->firstOrFail(); $type=AppointmentType::firstOrFail();
  $this->actingAs($user)->post('/citas/registrar',['dni'=>'70001111','patient_name'=>'Juan Carlos Perez Lopez','first_names'=>'Juan Carlos','paternal_surname'=>'Perez','maternal_surname'=>'Lopez','medical_record_number'=>'HC-1001','phone'=>'999888777','appointment_type_id'=>$type->id,'scheduled_at'=>now()->addHour()->format('Y-m-d H:i:s')])->assertSessionHas('success');
  $this->assertDatabaseHas('appointments',['appointment_type_id'=>$type->id,'created_by'=>$user->id]);
  $this->assertDatabaseHas('patients',['dni'=>'70001111','first_names'=>'Juan Carlos','paternal_surname'=>'Perez','maternal_surname'=>'Lopez','medical_record_number'=>'HC-1001']);
 }
 public function test_custom_role_controls_module_access():void {
  $this->seed();
  $role=CustomRole::create(['name'=>'Recepción especial','modules'=>['citas'],'active'=>true]);
  $user=User::where('role','portero')->firstOrFail(); $user->update(['custom_role_id'=>$role->id]);
  $this->actingAs($user)->get('/portal/citas')->assertOk();
  $this->actingAs($user)->get('/portal/portero')->assertForbidden();
 }
 public function test_admin_can_create_service_and_professional():void {
  $this->seed(); $admin=User::where('role','administrador')->firstOrFail();
  $this->actingAs($admin)->post('/administracion/servicios',['name'=>'Cardiología','code'=>'CARD','location'=>'Consultorio 8'])->assertSessionHas('success');
  $service=Service::where('code','CARD')->firstOrFail();
  $this->actingAs($admin)->post('/administracion/profesionales',['name'=>'Dra. Cardiología','email'=>'cardio@hospital.test','password'=>'password123','service_id'=>$service->id])->assertSessionHas('success');
  $this->assertDatabaseHas('users',['email'=>'cardio@hospital.test','service_id'=>$service->id]);
 }
 public function test_professional_can_complete_confirmed_appointment_from_own_service():void {
  $this->seed(); $professional=User::where('role','profesional')->firstOrFail();
  $type=AppointmentType::where('service_id',$professional->service_id)->firstOrFail();
  $appointment=Appointment::create(['code'=>'TEST-PRO-1','patient_id'=>\App\Models\Patient::firstOrFail()->id,'appointment_type_id'=>$type->id,'professional_id'=>$professional->id,'created_by'=>$professional->id,'scheduled_at'=>now(),'status'=>'confirmada']);
  $this->actingAs($professional)->put('/citas/'.$appointment->id.'/atender')->assertSessionHas('success');
  $this->assertDatabaseHas('appointments',['id'=>$appointment->id,'status'=>'atendida']);
  $this->assertDatabaseHas('appointment_status_history',['appointment_id'=>$appointment->id,'to_status'=>'atendida','changed_by'=>$professional->id]);
 }
 public function test_porter_confirmation_registers_entry_and_releases_patient_to_professional():void {
  $this->seed(); $professional=User::where('role','profesional')->firstOrFail(); $porter=User::where('role','portero')->firstOrFail();
  $type=AppointmentType::where('service_id',$professional->service_id)->firstOrFail();
  $patient=\App\Models\Patient::firstOrFail();
  $appointment=Appointment::create(['code'=>'TEST-FLOW-1','patient_id'=>$patient->id,'appointment_type_id'=>$type->id,'professional_id'=>$professional->id,'created_by'=>$professional->id,'scheduled_at'=>now(),'status'=>'programada']);
  $this->actingAs($professional)->get('/portal/servicio')->assertOk()->assertDontSee('TEST-FLOW-1');
  $this->actingAs($porter)->put('/citas/'.$appointment->id.'/confirmar',['access_point'=>'Puerta de consulta externa'])->assertSessionHas('success');
  $this->assertDatabaseHas('appointments',['id'=>$appointment->id,'status'=>'confirmada','confirmed_by'=>$porter->id,'access_point'=>'Puerta de consulta externa']);
  $this->assertDatabaseHas('access_logs',['appointment_id'=>$appointment->id,'movement'=>'ingreso','registered_by'=>$porter->id]);
  $this->assertDatabaseHas('appointment_status_history',['appointment_id'=>$appointment->id,'to_status'=>'confirmada','changed_by'=>$porter->id]);
  $this->actingAs($professional)->get('/portal/servicio')->assertOk()->assertSee($patient->full_name);
 }
 public function test_saved_dark_theme_is_applied_before_page_render():void {
  $this->seed(); $user=User::where('role','administrador')->firstOrFail();
  $this->actingAs($user)->withUnencryptedCookie('hospital_theme','dark')->get('/portal/administrador')->assertOk()->assertSee('<html data-theme="dark"',false);
 }
 public function test_passkey_options_use_the_current_localhost_domain():void {
  $request=\Illuminate\Http\Request::create('http://localhost:8000/user/passkeys/options');
  (new \App\Http\Middleware\ConfigurePasskeyOrigin())->handle($request,fn()=>response('ok'));
  $this->assertSame('localhost',config('passkeys.relying_party_id'));
  $this->assertSame(['http://localhost:8000'],config('passkeys.allowed_origins'));
 }
 public function test_dni_lookup_returns_full_name_for_appointment_form():void {
  $this->seed(); $user=User::where('role','admision')->firstOrFail();
  Http::fake(['*'=>Http::response(['data'=>['nombres'=>'JUAN CARLOS','apellido_paterno'=>'PEREZ','apellido_materno'=>'LOPEZ']],200)]);
  $this->actingAs($user)->getJson('/api/consultar-dni/71524353')->assertOk()->assertJson(['dni'=>'71524353','nombre_completo'=>'Juan Carlos Perez Lopez','nombres'=>'Juan Carlos','apellido_paterno'=>'Perez','apellido_materno'=>'Lopez']);
 }
}
