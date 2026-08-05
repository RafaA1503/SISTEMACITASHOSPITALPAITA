<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage;
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
  $this->actingAs($user)->post('/citas/registrar',['dni'=>'70001111','patient_name'=>'Paciente Prueba','appointment_type_id'=>$type->id,'scheduled_at'=>now()->addHour()->format('Y-m-d H:i:s')])->assertSessionHas('success');
  $this->assertDatabaseHas('appointments',['appointment_type_id'=>$type->id,'created_by'=>$user->id]);
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
 }
 public function test_saved_dark_theme_is_applied_before_page_render():void {
  $this->seed(); $user=User::where('role','administrador')->firstOrFail();
  $this->actingAs($user)->withCookie('hospital_theme','dark')->get('/portal/administrador')->assertOk()->assertSee('<html data-theme="dark"',false);
 }
}
