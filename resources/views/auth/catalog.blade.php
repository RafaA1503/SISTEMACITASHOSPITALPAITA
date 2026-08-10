<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Servicios y profesionales | Hospital Nuestra Señora de las Mercedes</title>
 <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
 @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body><div class="settings-page">
 <header><a href="{{ route('portal','administrador') }}">Volver</a><strong>Servicios y profesionales</strong><a href="{{ route('admin.roles') }}">Roles</a></header>
 <main>
  @if(session('success'))<div class="success-banner">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="auth-error">{{ $errors->first() }}</div>@endif
  <div class="admin-heading"><div><p>CONFIGURACION CLINICA</p><h1>Servicios y profesionales</h1><span>Cada profesional ve unicamente los pacientes asignados a su servicio.</span></div></div>
  <div class="account-grid"><section class="account-card">
   <h2>Crear profesional</h2><p>Crea sus credenciales y asignalo a un servicio del catalogo importado.</p>
   <form method="POST" action="{{ route('admin.professionals.store') }}" class="ajax-form" data-list-container="#professionalsList" data-reset-on-success="1">@csrf
    <label>Nombre completo<input name="name" required placeholder="Dr. Nombre Apellido"></label>
    <label>DNI<input name="dni" required pattern="[0-9]{8}" maxlength="8" placeholder="8 dígitos"></label>
    <label>Correo institucional<input name="email" type="email" required></label>
    <label>Contrasena inicial<input name="password" type="password" minlength="8" required></label>
    <label>Servicio<select name="service_id" required><option value="">Seleccionar</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->legacy_id }} - {{ $service->name }}</option>@endforeach</select></label>
    <button class="login-primary">Crear profesional</button>
   </form>
  </section></div>
  <section class="portal-panel"><div class="panel-title"><div><h2>Programar turnos de profesionales</h2><p>Jornadas importadas de jornadaslaborales (SIGESA): profesional, servicio, área, jornada y fecha.</p></div></div>
   <form method="POST" action="{{ route('admin.professionals.schedule.store') }}" id="scheduleForm" class="appointment-form ajax-form" data-list-container="#schedulesTableBody" data-create-url="{{ route('admin.professionals.schedule.store') }}" data-update-url-template="{{ route('admin.professionals.schedule.update', ['schedule' => '__ID__']) }}">@csrf
    <input type="hidden" name="_method" id="scheduleFormMethod" value="">
    <label>Profesional<select name="professional_id" required><option value="">Seleccionar</option>@foreach($professionals as $professional)<option value="{{ $professional->id }}">{{ $professional->name }} — {{ $professional->service?->name }}</option>@endforeach</select></label>
    <label>Servicio<select name="service_id" required><option value="">Seleccionar</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->legacy_id }} - {{ $service->name }}</option>@endforeach</select></label>
    <label>Área<select name="service_area_id"><option value="">Sin área específica</option>@foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->service->name }} - {{ $area->name }}</option>@endforeach</select></label>
    <label>Jornada<select name="work_shift_id" required><option value="">Seleccionar</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->abbreviation ? $shift->abbreviation.' - ' : '' }}{{ $shift->name }} ({{ $shift->starts_at?->format('H:i') }}–{{ $shift->ends_at?->format('H:i') }}){{ $shift->service ? ' · '.$shift->service->name : '' }}</option>@endforeach</select></label>
    <label>Fecha<input name="scheduled_date" type="date" min="{{ today()->format('Y-m-d') }}" required></label>
    <label>Observación<input name="notes" maxlength="500"></label>
    <div class="form-wide schedule-form-actions"><button class="primary-btn" id="scheduleFormSubmit">Registrar turno</button><button type="button" class="secondary-btn" id="scheduleFormCancel" hidden>Cancelar edición</button></div>
   </form>
   <div class="portal-table"><table><thead><tr><th>FECHA</th><th>PROFESIONAL</th><th>SERVICIO / ÁREA</th><th>JORNADA</th><th>ACCIÓN</th></tr></thead><tbody id="schedulesTableBody">@forelse($schedules as $schedule)@include('auth.partials.schedule-row',['schedule'=>$schedule])@empty<tr id="schedulesEmptyRow" data-empty-placeholder><td colspan="5" class="portal-empty">No hay turnos programados.</td></tr>@endforelse</tbody></table></div>
  </section>
  <div class="admin-heading catalog-heading"><div><h1>Catalogo de servicios</h1><span>Catalogo de solo lectura: se conserva el numero original, sin codigos SIG generados.</span></div></div>
  <section class="portal-panel">
   <div class="catalog-service-tools"><input id="serviceCatalogSearch" type="search" placeholder="Buscar por numero, servicio, especialidad o tipo..."></div>
   <div class="admin-services" id="serviceCatalog">
    @forelse($services as $service)
     <article data-service-search="{{ mb_strtolower($service->name.' '.$service->legacy_id.' '.($service->specialty?->name ?? '').' '.($service->type?->name ?? '')) }}">
      <span>+</span><div><h3>{{ $service->name }}</h3><p>{{ $service->specialty?->name ?? 'Sin especialidad' }} · {{ $service->type?->name ?? 'Sin tipo' }} · {{ $service->areas_count }} area(s)</p><small>{{ $service->active ? 'Activo' : 'Inactivo' }}{{ $service->report_enabled ? ' · Genera reporte' : '' }}</small></div><b>{{ $service->legacy_id }} - {{ $service->name }}</b>
     </article>
    @empty<p>No hay servicios importados.</p>@endforelse
   </div>
   <p id="serviceCatalogEmpty" hidden>No se encontraron servicios con ese filtro.</p>
  </section>
  <div class="user-admin-list catalog-professionals" id="professionalsList">@foreach($professionals as $professional)@include('auth.partials.professional-card',['professional'=>$professional])@endforeach</div>
 </main>
</div>
</body></html>
