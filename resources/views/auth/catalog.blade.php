<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Servicios y profesionales | Hospital La Merced</title>
 <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
 @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body><div class="settings-page">
 <header><a href="{{ route('portal','administrador') }}">← Volver</a><strong>Servicios y profesionales</strong><a href="{{ route('admin.roles') }}">Roles</a></header>
 <main>
  @if(session('success'))<div class="success-banner">✓ {{ session('success') }}</div>@endif
  @if($errors->any())<div class="auth-error">{{ $errors->first() }}</div>@endif
  <div class="admin-heading"><div><p>CONFIGURACIÓN CLÍNICA</p><h1>Servicios y profesionales</h1><span>Cada profesional verá únicamente los pacientes asignados a su servicio.</span></div></div>
  <div class="account-grid">
   <section class="account-card">
    <h2>Crear servicio adicional</h2>
    <p>Registra únicamente los datos existentes en la estructura de servicios de la base hospitalaria.</p>
    <form method="POST" action="{{ route('admin.services.store') }}">@csrf
     <label>Nombre del servicio<input name="name" value="{{ old('name') }}" required placeholder="Ej. Cardiología"></label>
     <label>Especialidad<select name="specialty_id"><option value="">Sin especialidad</option>@foreach($specialties as $specialty)<option value="{{ $specialty->id }}" @selected(old('specialty_id')==$specialty->id)>{{ $specialty->name }}</option>@endforeach</select></label>
     <label>Tipo de servicio<select name="service_type_id"><option value="">Sin tipo asignado</option>@foreach($serviceTypes as $type)<option value="{{ $type->id }}" @selected(old('service_type_id')==$type->id)>{{ $type->name }}</option>@endforeach</select></label>
     <label class="checkbox-field"><input type="hidden" name="report_enabled" value="0"><input type="checkbox" name="report_enabled" value="1" @checked(old('report_enabled'))> Es un servicio de reporte</label>
     <label class="checkbox-field"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active','1'))> Servicio activo</label>
     <label>Observación<textarea name="notes" rows="3" placeholder="Observación del servicio">{{ old('notes') }}</textarea></label>
     <button class="login-primary">Crear servicio</button>
    </form>
   </section>
   <section class="account-card">
    <h2>Crear profesional</h2><p>Crea sus credenciales y asígnalo al servicio correspondiente.</p>
    <form method="POST" action="{{ route('admin.professionals.store') }}">@csrf
     <label>Nombre completo<input name="name" required placeholder="Dr. Nombre Apellido"></label>
     <label>Correo institucional<input name="email" type="email" required></label>
     <label>Contraseña inicial<input name="password" type="password" minlength="8" required></label>
     <label>Servicio<select name="service_id" required><option value="">Seleccionar</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->code }} — {{ $service->name }}</option>@endforeach</select></label>
     <button class="login-primary">Crear profesional</button>
    </form>
   </section>
  </div>
  <div class="admin-heading catalog-heading"><div><h1>Catálogo de servicios</h1><span>Los servicios importados conservan internamente su IdServicio original.</span></div></div>
  <section class="portal-panel">
   <div class="catalog-service-tools"><input id="serviceCatalogSearch" type="search" placeholder="Buscar por servicio, código, especialidad o tipo…"><select id="serviceCatalogSource"><option value="">Todos los orígenes</option><option value="sigesa">Importados de SIGESA</option><option value="local">Servicios locales</option></select></div>
   <div class="admin-services" id="serviceCatalog">
    @forelse($services as $service)
     <article data-service-search="{{ mb_strtolower($service->name.' '.$service->code.' '.($service->specialty?->name ?? '').' '.($service->type?->name ?? '')) }}" data-source="{{ $service->legacy_id ? 'sigesa' : 'local' }}">
      <span>✚</span><div><h3>{{ $service->name }}</h3><p>{{ $service->specialty?->name ?? 'Sin especialidad' }} · {{ $service->type?->name ?? 'Sin tipo' }} · {{ $service->areas_count }} área(s)</p><small>{{ $service->active ? 'Activo' : 'Inactivo' }}{{ $service->report_enabled ? ' · Genera reporte' : '' }}</small></div><b>{{ $service->legacy_id ? 'ID '.$service->legacy_id : 'LOCAL' }}</b>
     </article>
    @empty<p>No hay servicios configurados.</p>@endforelse
   </div>
   <p id="serviceCatalogEmpty" hidden>No se encontraron servicios con ese filtro.</p>
  </section>
  <div class="user-admin-list catalog-professionals">@foreach($professionals as $professional)<div class="user-admin-card"><div class="user-identity"><div class="avatar">{{ strtoupper(substr($professional->name,0,2)) }}</div><div><strong>{{ $professional->name }}</strong><small>{{ $professional->email }}</small></div></div><span>{{ $professional->service?->code }} — {{ $professional->service?->name }}</span><span>{{ $professional->active?'Activo':'Inactivo' }}</span></div>@endforeach</div>
 </main>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{const q=document.getElementById('serviceCatalogSearch'),source=document.getElementById('serviceCatalogSource'),cards=[...document.querySelectorAll('#serviceCatalog article')],empty=document.getElementById('serviceCatalogEmpty');const filter=()=>{const term=q.value.trim().toLocaleLowerCase('es'),origin=source.value;let visible=0;cards.forEach(card=>{const show=(!term||card.dataset.serviceSearch.includes(term))&&(!origin||card.dataset.source===origin);card.hidden=!show;if(show)visible++});empty.hidden=visible!==0};q.addEventListener('input',filter);source.addEventListener('change',filter)});</script>
</body></html>
