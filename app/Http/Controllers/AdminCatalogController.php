<?php
namespace App\Http\Controllers;
use App\Models\AppointmentType;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class AdminCatalogController extends Controller {
 private function admin(Request $r):void{abort_unless($r->user()->role==='administrador',403);}
 public function index(Request $r){$this->admin($r);return view('auth.catalog',['services'=>Service::with(['type','specialty'])->withCount(['appointmentTypes','areas'])->orderBy('name')->get(),'serviceTypes'=>ServiceType::where('active',true)->orderBy('name')->get(),'specialties'=>Specialty::where('active',true)->orderBy('name')->get(),'professionals'=>User::with('service')->whereNotNull('service_id')->orderBy('name')->get()]);}
 public function storeService(Request $r){
  $this->admin($r);
  $data=$r->validate([
   'name'=>'required|string|max:100|unique:services,name',
   'specialty_id'=>'nullable|exists:specialties,id',
   'service_type_id'=>'nullable|exists:service_types,id',
   'report_enabled'=>'nullable|boolean',
   'active'=>'nullable|boolean',
   'notes'=>'nullable|string|max:2000',
  ]);
  $code='LOCAL-'.Str::upper(Str::random(6));
  while(Service::where('code',$code)->exists()) $code='LOCAL-'.Str::upper(Str::random(6));
  $service=Service::create(['name'=>trim($data['name']),'code'=>$code,'specialty_id'=>$data['specialty_id']??null,'service_type_id'=>$data['service_type_id']??null,'category'=>'consulta','location'=>null,'report_enabled'=>(bool)($data['report_enabled']??false),'active'=>(bool)($data['active']??true),'notes'=>$data['notes']??null]);
  AppointmentType::create(['service_id'=>$service->id,'name'=>'Consulta de '.$service->name,'code'=>'C-'.$service->code,'duration_minutes'=>20,'requires_order'=>false,'active'=>true]);
  return back()->with('success','Servicio creado respetando la estructura del catálogo hospitalario.');
 }
 public function storeProfessional(Request $r){$this->admin($r);$data=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users,email','password'=>'required|string|min:8','service_id'=>'required|exists:services,id']);User::create(['name'=>$data['name'],'email'=>strtolower(trim($data['email'])),'password'=>$data['password'],'role'=>'profesional','service_id'=>$data['service_id'],'active'=>true]);return back()->with('success','Profesional creado y asignado al servicio.');}
}
