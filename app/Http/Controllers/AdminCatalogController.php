<?php
namespace App\Http\Controllers;
use App\Models\AppointmentType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
class AdminCatalogController extends Controller {
 private function admin(Request $r):void{abort_unless($r->user()->role==='administrador',403);}
 public function index(Request $r){$this->admin($r);return view('auth.catalog',['services'=>Service::with(['type','specialty'])->withCount(['appointmentTypes','areas'])->orderBy('name')->get(),'professionals'=>User::with('service')->whereNotNull('service_id')->orderBy('name')->get()]);}
 public function storeService(Request $r){
  $this->admin($r);
  $data=$r->validate([
   'name'=>'required|string|max:120|unique:services,name',
   'code'=>['required','string','max:12','alpha_dash','unique:services,code','not_regex:/^SIG-\d+$/i'],
   'location'=>'nullable|string|max:120'
  ],['code.not_regex'=>'Los códigos SIG-número están reservados para servicios importados de SIGESA.']);
  $service=Service::create(['name'=>trim($data['name']),'code'=>strtoupper(trim($data['code'])),'category'=>'consulta','location'=>$data['location']??null,'active'=>true]);
  AppointmentType::create(['service_id'=>$service->id,'name'=>'Consulta de '.$service->name,'code'=>'C-'.$service->code,'duration_minutes'=>20,'requires_order'=>false,'active'=>true]);
  return back()->with('success','Servicio local creado con su tipo de consulta.');
 }
 public function storeProfessional(Request $r){$this->admin($r);$data=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users,email','password'=>'required|string|min:8','service_id'=>'required|exists:services,id']);User::create(['name'=>$data['name'],'email'=>strtolower(trim($data['email'])),'password'=>$data['password'],'role'=>'profesional','service_id'=>$data['service_id'],'active'=>true]);return back()->with('success','Profesional creado y asignado al servicio.');}
}
