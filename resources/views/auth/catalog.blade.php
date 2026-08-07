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
   <form method="POST" action="{{ route('admin.professionals.store') }}">@csrf
    <label>Nombre completo<input name="name" required placeholder="Dr. Nombre Apellido"></label>
    <label>DNI<input name="dni" required pattern="[0-9]{8}" maxlength="8" placeholder="8 dígitos"></label>
    <label>Correo institucional<input name="email" type="email" required></label>
    <label>Contrasena inicial<input name="password" type="password" minlength="8" required></label>
    <label>Servicio<select name="service_id" required><option value="">Seleccionar</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->legacy_id }} - {{ $service->name }}</option>@endforeach</select></label>
    <button class="login-primary">Crear profesional</button>
   </form>
  </section></div>
  <section class="portal-panel"><div class="panel-title"><div><h2>Programar turnos de profesionales</h2><p>Jornadas importadas de jornadaslaborales (SIGESA): profesional, servicio, área, jornada y fecha.</p></div></div>
   <form method="POST" action="{{ route('admin.professionals.schedule.store') }}" class="appointment-form">@csrf
    <label>Profesional<select name="professional_id" required><option value="">Seleccionar</option>@foreach($professionals as $professional)<option value="{{ $professional->id }}">{{ $professional->name }} — {{ $professional->service?->name }}</option>@endforeach</select></label>
    <label>Servicio<select name="service_id" required><option value="">Seleccionar</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->legacy_id }} - {{ $service->name }}</option>@endforeach</select></label>
    <label>Área<select name="service_area_id"><option value="">Sin área específica</option>@foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->service->name }} - {{ $area->name }}</option>@endforeach</select></label>
    <label>Jornada<select name="work_shift_id" required><option value="">Seleccionar</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->abbreviation ? $shift->abbreviation.' - ' : '' }}{{ $shift->name }} ({{ $shift->starts_at?->format('H:i') }}–{{ $shift->ends_at?->format('H:i') }}){{ $shift->service ? ' · '.$shift->service->name : '' }}</option>@endforeach</select></label>
    <label>Fecha<input name="scheduled_date" type="date" min="{{ today()->format('Y-m-d') }}" required></label>
    <label>Observación<input name="notes" maxlength="500"></label>
    <button class="primary-btn form-wide">Registrar turno</button>
   </form>
   <div class="portal-table"><table><thead><tr><th>FECHA</th><th>PROFESIONAL</th><th>SERVICIO / ÁREA</th><th>JORNADA</th></tr></thead><tbody>@forelse($schedules as $schedule)<tr><td>{{ $schedule->scheduled_date->format('d/m/Y') }}</td><td>{{ $schedule->trabajador?->persona?->full_name ?? 'Sin nombre' }}</td><td>{{ $schedule->service->name }}{{ $schedule->area ? ' - '.$schedule->area->name : '' }}</td><td>{{ $schedule->shift->abbreviation ?: $schedule->shift->name }} ({{ $schedule->shift->starts_at?->format('H:i') }}–{{ $schedule->shift->ends_at?->format('H:i') }})</td></tr>@empty<tr><td colspan="4" class="portal-empty">No hay turnos programados.</td></tr>@endforelse</tbody></table></div>
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
  <div class="user-admin-list catalog-professionals">@foreach($professionals as $professional)<div class="user-admin-card"><div class="user-identity"><div class="avatar">{{ mb_strtoupper(mb_substr($professional->name,0,2,'UTF-8'),'UTF-8') }}</div><div><strong>{{ $professional->name }}</strong><small>{{ $professional->email }}</small></div></div><span>{{ $professional->service?->legacy_id }} - {{ $professional->service?->name }}</span><span>{{ $professional->active?'Activo':'Inactivo' }}</span></div>@endforeach</div>
 </main>
</div>
</body></html>
