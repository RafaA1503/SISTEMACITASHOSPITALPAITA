<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage;
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
}
