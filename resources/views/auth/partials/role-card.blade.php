<details class="role-card" data-row-id="{{ $item->id }}">
 <summary><span class="role-symbol">{{ mb_strtoupper(mb_substr($item->name,0,1,'UTF-8'),'UTF-8') }}</span><span class="role-card-copy"><strong>{{ $item->name }}</strong><small>{{ $item->users_count }} {{ $item->users_count===1?'usuario asignado':'usuarios asignados' }}</small></span><span class="role-tags">@foreach($item->modules as $module)<i>{{ $module==='portero'?'Acceso':'Administración' }}</i>@endforeach</span><b>⌄</b></summary>
 <div class="role-card-editor">
  <form method="POST" action="{{ route('admin.roles.update',$item) }}" class="ajax-form">@csrf @method('PUT')
   <label>Nombre del rol<input name="name" value="{{ $item->name }}" required></label>
   <fieldset class="module-permissions"><legend>Permisos del rol</legend>@foreach(['portero'=>'Control de Acceso de Pacientes','administrador'=>'Administración'] as $key=>$label)<label><input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key,$item->modules))><span>{{ $label }}</span></label>@endforeach</fieldset>
   <button class="login-primary"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>Guardar cambios</button>
  </form>
  <form method="POST" action="{{ route('admin.roles.destroy',$item) }}" class="ajax-form" data-confirm="¿Eliminar este rol? Los usuarios asociados volverán a su rol base.">@csrf @method('DELETE')<button class="role-delete" type="submit"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1.5 14a2 2 0 0 1-2 1.8h-7a2 2 0 0 1-2-1.8L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>Eliminar rol</button></form>
 </div>
</details>
