<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Reportes | Hospital Nuestra Señora de las Mercedes</title>
 <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
 @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body><div class="settings-page">
 <header class="report-page-header">
  <a class="settings-header-action settings-header-action--back" href="{{ route('portal','administrador') }}" aria-label="Volver al panel de administración">
   <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
   <span>Volver al panel</span>
  </a>
  <strong>Reportes</strong>
  <a class="settings-header-action settings-header-action--next" href="{{ route('admin.catalog') }}" aria-label="Ir a servicios">
   <span>Servicios</span>
   <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
  </a>
 </header>
 <main>
  <div class="admin-heading"><div><p>SEGUIMIENTO Y CONTROL</p><h1>Atención, faltas y puntualidad</h1><span>Resumen de pacientes atendidos, inasistencias y llegadas tarde por rango de fechas.</span></div></div>

  <form method="GET" action="{{ route('admin.reports') }}" class="report-filters">
   <label>Desde<input type="date" name="from" value="{{ $filters['from'] }}"></label>
   <label>Hasta<input type="date" name="to" value="{{ $filters['to'] }}"></label>
   <label>Servicio<select name="service_id"><option value="">Todos los servicios</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected($filters['service_id']==$service->id)>{{ $service->name }}</option>@endforeach</select></label>
   <label>Paciente hospitalizado<select name="hospital_patient"><option value="">Todas las visitas</option>@foreach($hospitalVisitPatients as $patient)<option value="{{ $patient }}" @selected($filters['hospital_patient']===$patient)>{{ $patient }}</option>@endforeach</select></label>
   <button class="login-primary">Filtrar</button>
   <a class="outline-btn" href="{{ route('admin.reports.excel', $filters) }}" download>⇩ Excel</a>
   <a class="outline-btn" href="{{ route('admin.reports.pdf', $filters) }}" download>⇩ PDF</a>
  </form>

  <section class="module-metrics report-metrics">
   <article><small>CITAS EN EL RANGO</small><strong>{{ $metrics['total'] }}</strong><p>Total programadas</p></article>
   <article><small>ATENDIDOS</small><strong>{{ $metrics['attended'] }}</strong><p>{{ $metrics['attendance_rate'] }}% del total</p></article>
   <article><small>FALTAS</small><strong>{{ $metrics['no_show'] }}</strong><p>{{ $metrics['no_show_rate'] }}% del total</p></article>
   <article><small>PUNTUALIDAD</small><strong>{{ $metrics['punctuality_rate'] }}%</strong><p>{{ $metrics['on_time'] }} puntuales · {{ $metrics['late'] }} con tardanza</p></article>
  </section>

  @php($statusTotal = max(1, array_sum($charts['statuses'])))
  @php($accessPeak = max(1, $charts['days']->map(fn ($day) => $day['ingresos'] + $day['salidas'])->max()))
  <section class="report-charts" aria-label="Gráficos estadísticos del reporte">
   <article class="report-chart-card">
    <div class="report-chart-head"><div><small>ESTADO DE CITAS</small><h2>Distribución de atenciones</h2></div><strong>{{ $metrics['total'] }}</strong></div>
    <div class="status-chart">
     <div class="status-donut" style="--programadas: {{ round($charts['statuses']['programadas'] / $statusTotal * 100) }}%; --confirmadas: {{ round($charts['statuses']['confirmadas'] / $statusTotal * 100) }}%; --atendidas: {{ round($charts['statuses']['atendidas'] / $statusTotal * 100) }}%;"><b>{{ $metrics['total'] }}</b><span>citas</span></div>
     <div class="chart-legend"><span><i class="chart-programadas"></i>Programadas <b>{{ $charts['statuses']['programadas'] }}</b></span><span><i class="chart-confirmadas"></i>Confirmadas <b>{{ $charts['statuses']['confirmadas'] }}</b></span><span><i class="chart-atendidas"></i>Atendidas <b>{{ $charts['statuses']['atendidas'] }}</b></span><span><i class="chart-faltas"></i>Faltas <b>{{ $charts['statuses']['faltas'] }}</b></span></div>
    </div>
   </article>
   <article class="report-chart-card">
    <div class="report-chart-head"><div><small>PORTERÍA</small><h2>Movimientos por día</h2></div><strong>{{ $charts['accesses'] }}</strong></div>
    <div class="access-chart">@forelse($charts['days'] as $day)<div class="access-bar"><div class="access-bar-stack" title="{{ $day['ingresos'] }} ingreso(s), {{ $day['salidas'] }} salida(s)"><i class="access-ingresos" style="height:{{ max(4, round($day['ingresos'] / $accessPeak * 100)) }}%"></i><i class="access-salidas" style="height:{{ max(0, round($day['salidas'] / $accessPeak * 100)) }}%"></i></div><small>{{ $day['label'] }}</small></div>@empty<p class="chart-empty">Sin movimientos en el rango.</p>@endforelse</div>
    <p class="chart-key"><span><i class="access-ingresos"></i>Ingresos</span><span><i class="access-salidas"></i>Salidas</span></p>
   </article>
   <article class="report-chart-card hospital-chart-card">
    <div class="report-chart-head"><div><small>HOSPITALIZACIÓN</small><h2>Resumen de visitas</h2></div></div>
    <div class="hospital-chart-stats"><div><strong>{{ $charts['hospitalized'] }}</strong><span>Hospitalizados activos</span></div><div><strong>{{ $charts['visitors'] }}</strong><span>Con visitante registrado</span></div><div><strong>{{ $charts['accesses'] }}</strong><span>Movimientos de acceso</span></div></div>
    <p class="chart-note">Incluye acompañantes de la atención y visitas registradas por Portería.</p>
   </article>
  </section>

  <section class="portal-panel">
   <div class="panel-title"><div><h2>Visitas hospitalarias registradas</h2><p>Entradas y salidas registradas por Portería para pacientes hospitalizados.</p></div></div>
   <div class="portal-table"><table><thead><tr><th>FECHA / HORA</th><th>PACIENTE HOSPITALIZADO</th><th>VISITANTE</th><th>PARENTESCO</th><th>ESTADO</th></tr></thead><tbody>
    @forelse($hospitalVisits as $visit)
    <tr>
     <td><strong>{{ \Illuminate\Support\Carbon::parse($visit->registered_at)->format('d/m/Y') }}</strong><small>Entrada {{ \Illuminate\Support\Carbon::parse($visit->registered_at)->format('H:i') }}</small></td>
     <td><strong>{{ $visit->patient_label }}</strong><small>Visita hospitalaria</small></td>
     <td><strong>{{ $visit->visitor_name }}</strong><small>{{ $visit->visitor_dni ? 'DNI '.$visit->visitor_dni : 'DNI no registrado' }}</small></td>
     <td>{{ $visit->relationship }}</td>
     <td><span class="status {{ $visit->checked_out_at ? 'confirmada' : 'ingreso' }}">{{ $visit->checked_out_at ? 'Salida '.\Illuminate\Support\Carbon::parse($visit->checked_out_at)->format('H:i') : 'Visita activa' }}</span></td>
    </tr>
    @empty
    <tr><td colspan="5" class="portal-empty">No hay visitas hospitalarias registradas en ese rango.</td></tr>
    @endforelse
   </tbody></table></div>
  </section>

  <section class="portal-panel">
   <div class="panel-title"><div><h2>Pacientes hospitalizados y visitas</h2><p>Hospitalizaciones activas y familiar o acompañante registrado en la atención.</p></div></div>
   <div class="portal-table"><table><thead><tr><th>INGRESO</th><th>PACIENTE HOSPITALIZADO</th><th>ESTADO</th><th>VISITANTE / ACOMPAÑANTE</th><th>CONTACTO Y OBSERVACIÓN</th></tr></thead><tbody>
    @forelse($hospitalizations as $hospitalization)
    <tr>
     <td><strong>{{ \Illuminate\Support\Carbon::parse($hospitalization->FechaIngreso)->format('d/m/Y') }}</strong><small>Atención #{{ $hospitalization->IdAtencion }}</small></td>
     <td><strong>{{ $hospitalization->patient_name ?: 'Paciente sin nombre' }}</strong><small>{{ $hospitalization->dni ? 'DNI '.$hospitalization->dni : 'Sin DNI registrado' }}</small></td>
     <td><span class="status {{ empty($hospitalization->FechaEgreso) || $hospitalization->FechaEgreso === '0000-00-00' ? 'ingreso' : 'confirmada' }}">{{ empty($hospitalization->FechaEgreso) || $hospitalization->FechaEgreso === '0000-00-00' ? 'Hospitalizado' : 'Egresado' }}</span></td>
     <td><strong>{{ $hospitalization->visitor_name ?: 'Sin visitante registrado' }}</strong><small>{{ $hospitalization->visitor_name ? 'Acompañante de la atención' : 'Aún no se registró acompañante' }}</small></td>
     <td>{{ $hospitalization->visitor_phone ?: 'Sin teléfono' }}<small>{{ $hospitalization->visitor_notes ?: 'Sin observación' }}</small></td>
    </tr>
    @empty
    <tr><td colspan="5" class="portal-empty">No hay pacientes hospitalizados ni visitantes registrados en ese rango.</td></tr>
    @endforelse
   </tbody></table></div>
  </section>

  <section class="portal-panel">
   <div class="panel-title"><div><h2>Detalle de citas</h2><p>Tardanza calculada contra la hora programada, con {{ 15 }} minutos de tolerancia.</p></div></div>
   <div class="portal-table"><table><thead><tr><th>FECHA / HORA</th><th>PACIENTE</th><th>SERVICIO</th><th>PROFESIONAL</th><th>LLEGADA</th><th>PUNTUALIDAD</th><th>ESTADO</th></tr></thead><tbody>
    @forelse($appointments as $a)
    <tr>
     <td><strong>{{ $a->scheduled_at->format('d/m/Y') }}</strong><small>{{ $a->scheduled_at->format('H:i') }}</small></td>
     <td><strong>{{ $a->patient->full_name }}</strong><small>DNI {{ $a->patient->dni }}</small></td>
     <td>{{ $a->type->service->name }}<small>{{ $a->type->name }}</small></td>
     <td>{{ $a->professional?->name ?? 'Por asignar' }}</td>
     <td>{{ $a->confirmed_at?->format('H:i') ?? '—' }}</td>
     <td>@if($a->punctuality==='puntual')<span class="status atendida">Puntual</span>@elseif($a->punctuality==='tardanza')<span class="status retraso">Tardanza {{ $a->late_minutes }} min</span>@else<span class="waiting-label">—</span>@endif</td>
     <td><span class="status {{ $a->status }}">{{ ucfirst(str_replace('_',' ',$a->status)) }}</span></td>
    </tr>
    @empty
    <tr><td colspan="7" class="portal-empty">No hay citas registradas en ese rango.</td></tr>
    @endforelse
   </tbody></table></div>
  </section>

  <section class="portal-panel">
   <div class="panel-title"><div><h2>Ingresos y salidas registrados</h2><p>Movimientos reales registrados por Portería en el rango seleccionado.</p></div></div>
   <div class="portal-table"><table><thead><tr><th>FECHA / HORA</th><th>PERSONA</th><th>TIPO</th><th>PUNTO DE ACCESO</th><th>MOTIVO</th></tr></thead><tbody>
    @forelse($accessEntries as $entry)
    <tr>
     <td><strong>{{ \Illuminate\Support\Carbon::parse($entry->registered_at)->format('d/m/Y') }}</strong><small>{{ \Illuminate\Support\Carbon::parse($entry->registered_at)->format('H:i') }}</small></td>
     <td><strong>{{ $entry->patient_name ?: 'Paciente no identificado' }}</strong><small>{{ $entry->dni ? 'DNI '.$entry->dni : 'Sin DNI registrado' }}</small></td>
     <td><span class="status {{ $entry->movement === 'ingreso' ? 'ingreso' : 'confirmada' }}">{{ ucfirst($entry->movement) }}</span><small>{{ ucfirst($entry->person_type ?? 'paciente') }}</small></td>
     <td>{{ $entry->access_point ?: 'No especificado' }}</td>
     <td>{{ $entry->reason ?: 'Sin observación' }}</td>
    </tr>
    @empty
    <tr><td colspan="5" class="portal-empty">No hay ingresos o salidas registrados en ese rango.</td></tr>
    @endforelse
   </tbody></table></div>
  </section>
 </main>
</div>
</body></html>
