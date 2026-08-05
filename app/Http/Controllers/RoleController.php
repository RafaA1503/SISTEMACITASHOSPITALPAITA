<?php
namespace App\Http\Controllers;
use App\Models\CustomRole;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
class RoleController extends Controller {
 private function admin(Request $r):void{abort_unless($r->user()->role==='administrador',403);}
 public function index(Request $r){$this->admin($r);return view('auth.roles',['roles'=>CustomRole::withCount('users')->orderBy('name')->get(),'users'=>User::with('customRole','service')->orderBy('name')->get(),'services'=>Service::where('active',true)->get()]);}
 public function store(Request $r){$this->admin($r);$data=$r->validate(['name'=>'required|string|max:80|unique:custom_roles,name','modules'=>'required|array|min:1','modules.*'=>'in:portero,citas,servicio,administrador']);CustomRole::create(['name'=>$data['name'],'modules'=>$data['modules'],'active'=>true]);return back()->with('success','Rol creado correctamente.');}
 public function assign(Request $r,User $user){$this->admin($r);$data=$r->validate(['custom_role_id'=>'nullable|exists:custom_roles,id','service_id'=>'nullable|exists:services,id']);$user->update($data);return back()->with('success','Rol y servicio asignados.');}
}
