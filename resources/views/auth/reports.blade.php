<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Reportes | Hospital Nuestra Señora de las Mercedes</title>
 <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
 @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body><div class="settings-page">
 <header><a href="{{ route('portal','administrador') }}">← Volver</a><strong>Reportes</strong><a href="{{ route('admin.catalog') }}">Servicios</a></header>
 <main>
  <div class="admin-heading"><div><p>SEGUIMIENTO Y CONTROL</p><h1>Atención, faltas y puntualidad</h1><span>Resumen de pacientes atendidos, inasistencias y llegadas tarde por rango de fechas.</span></div></div>

  <form method="GET" action="{{ route('admin.reports') }}" class="report-filters">
   <label>Desde<input type="date" name="from" value="{{ $filters['from'] }}"></label>
   <label>Hasta<input type="date" name="to" value="{{ $filters['to'] }}"></label>
   <label>Servicio<select name="service_id"><option value="">Todos los servicios</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected($filters['service_id']==$service->id)>{{ $service->name }}</option>@endforeach</select></label>
   <button class="login-primary">Filtrar</button>
   <a class="outline-btn" href="{{ route('admin.reports.excel', $filters) }}">⇩ Excel</a>
   <a class="outline-btn" href="{{ route('admin.reports.pdf', $filters) }}">⇩ PDF</a>
  </form>

  <section class="module-metrics report-metrics">
   <article><small>CITAS EN EL RANGO</small><strong>{{ $metrics['total'] }}</strong><p>Total programadas</p></article>
   <article><small>ATENDIDOS</small><strong>{{ $metrics['attended'] }}</strong><p>{{ $metrics['attendance_rate'] }}% del total</p></article>
   <article><small>FALTAS</small><strong>{{ $metrics['no_show'] }}</strong><p>{{ $metrics['no_show_rate'] }}% del total</p></article>
   <article><small>PUNTUALIDAD</small><strong>{{ $metrics['punctuality_rate'] }}%</strong><p>{{ $metrics['on_time'] }} puntuales · {{ $metrics['late'] }} con tardanza</p></article>
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
 </main>
</div>
</body></html>
