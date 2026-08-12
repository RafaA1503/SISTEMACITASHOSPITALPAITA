<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page{margin:18mm 14mm 16mm}
    body{font-family:'DejaVu Sans',sans-serif;font-size:10px;color:#17365f;margin:0;padding:0}
    body>h1,body>.subtitle{display:none}
    h1{font-size:16px;margin:0 0 2px}
    .subtitle{font-size:9px;color:#6f7d8d;margin:0 0 16px}
    .pdf-brand{width:100%;border-collapse:collapse;margin:0 0 14px;border-bottom:4px solid #d5aa37}.pdf-brand td{vertical-align:middle}.pdf-brand .logo-cell{width:56px;padding:0 12px 10px 0}.pdf-brand img{width:48px;height:48px;object-fit:contain}.pdf-brand h1{margin:0;color:#0d3987;font-size:18px;letter-spacing:.1px}.pdf-brand p{margin:4px 0 0;color:#617491;font-size:9px}.pdf-brand .report-tag{text-align:right;padding-bottom:10px;color:#a27c18;font-size:8px;font-weight:bold;letter-spacing:1px;text-transform:uppercase}
    table{width:100%;border-collapse:collapse}
    .metrics{margin-bottom:16px}
    .metrics{border-collapse:separate;border-spacing:5px;margin:0 0 15px}.metrics td{padding:10px;border:1px solid #d9e2f0;border-radius:5px;text-align:center;background:#f5f8fd}.metrics strong{display:block;font-size:15px;color:#0d3987}.metrics span{display:block;font-size:8px;color:#6f7d8d;text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
    .detail{margin-bottom:16px}.detail th{background:#0d3987;font-size:8px;text-transform:uppercase;letter-spacing:.5px;color:#fff;padding:7px 8px;border-bottom:2px solid #d5aa37;text-align:left}.detail td{padding:7px 8px;border-bottom:1px solid #dce5f0;font-size:9px}.detail tr:nth-child(even) td{background:#f5f8fd}
    .footer{position:fixed;bottom:-6mm;left:0;right:0;padding-top:5px;border-top:1px solid #dce5f0;font-size:8px;color:#6f7d8d}
    h2{font-size:12px;margin:20px 0 7px;color:#0d3987;border-left:4px solid #d5aa37;padding-left:7px}
    .section-note{margin:0 0 8px;color:#6f7d8d;font-size:8px}
    .status{font-weight:bold;color:#168764}
    .page-break{page-break-before:always}
</style>
</head>
<body>
    @php($pdfLogo = base64_encode(file_get_contents(public_path('logo-hospital-la-merced.png'))))
    <table class="pdf-brand"><tr><td class="logo-cell"><img src="data:image/png;base64,{{ $pdfLogo }}" alt="Hospital"></td><td><h1>Hospital Nuestra Señora de las Mercedes</h1><p>Red de Salud Paita - Piura · Reporte institucional de atención</p></td><td class="report-tag">Reporte generado<br>{{ now()->format('d/m/Y H:i') }}</td></tr></table>
    <h1>Hospital Nuestra Señora de las Mercedes — Reporte de atención</h1>
    <p class="subtitle">Del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }} · Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table class="metrics">
        <tr>
            <td><strong>{{ $metrics['total'] }}</strong><span>Citas en el rango</span></td>
            <td><strong>{{ $metrics['attended'] }}</strong><span>Atendidos ({{ $metrics['attendance_rate'] }}%)</span></td>
            <td><strong>{{ $metrics['no_show'] }}</strong><span>Faltas ({{ $metrics['no_show_rate'] }}%)</span></td>
            <td><strong>{{ $metrics['punctuality_rate'] }}%</strong><span>Puntualidad</span></td>
            <td><strong>{{ $metrics['late'] }}</strong><span>Con tardanza</span></td>
        </tr>
    </table>

    <table class="detail">
        <thead>
            <tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>DNI</th><th>Servicio</th><th>Atención</th><th>Profesional</th><th>Llegada</th><th>Puntualidad</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse($appointments as $a)
            <tr>
                <td>{{ $a->scheduled_at->format('d/m/Y') }}</td>
                <td>{{ $a->scheduled_at->format('H:i') }}</td>
                <td>{{ $a->patient->full_name }}</td>
                <td>{{ $a->patient->dni }}</td>
                <td>{{ $a->type->service->name }}</td>
                <td>{{ $a->type->name }}</td>
                <td>{{ $a->professional?->name ?? 'Por asignar' }}</td>
                <td>{{ $a->confirmed_at?->format('H:i') ?? '—' }}</td>
                <td>@if($a->punctuality==='puntual')Puntual@elseif($a->punctuality==='tardanza')Tardanza {{ $a->late_minutes }} min@else—@endif</td>
                <td>{{ ucfirst(str_replace('_',' ',$a->status)) }}</td>
            </tr>
            @empty
            <tr><td colspan="10" style="text-align:center;padding:14px;color:#9aa4ab">No hay citas registradas en ese rango.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Hospital Nuestra Señora de las Mercedes · Sistema de gestión hospitalaria</p>
    <h2>Visitas hospitalarias registradas</h2>
    <p class="section-note">Entradas y salidas registradas por Porter&iacute;a en el rango seleccionado.</p>
    @if(filled($hospitalPatient ?? null))<p class="section-note"><strong>Paciente seleccionado:</strong> {{ $hospitalPatient }}</p>@endif
    <table class="detail"><thead><tr><th>Fecha / hora</th><th>Paciente hospitalizado</th><th>Visitante</th><th>DNI</th><th>Parentesco</th><th>Estado</th></tr></thead><tbody>
        @forelse($hospitalVisits as $visit)
        <tr><td>{{ \Illuminate\Support\Carbon::parse($visit->registered_at)->format('d/m/Y H:i') }}</td><td>{{ $visit->patient_label }}</td><td>{{ $visit->visitor_name }}</td><td>{{ $visit->visitor_dni ?: '—' }}</td><td>{{ $visit->relationship }}</td><td class="status">{{ $visit->checked_out_at ? 'Salida '.\Illuminate\Support\Carbon::parse($visit->checked_out_at)->format('H:i') : 'Visita activa' }}</td></tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:14px;color:#9aa4ab">No hay visitas hospitalarias registradas en ese rango.</td></tr>
        @endforelse
    </tbody></table>

    <h2>Pacientes hospitalizados</h2>
    <p class="section-note">Hospitalizaciones vigentes provenientes de la estructura asistencial.</p>
    <table class="detail"><thead><tr><th>Ingreso</th><th>Paciente</th><th>DNI</th><th>Estado</th><th>Acompa&ntilde;ante</th><th>Contacto / observaci&oacute;n</th></tr></thead><tbody>
        @forelse($hospitalizations as $hospitalization)
        <tr><td>{{ \Illuminate\Support\Carbon::parse($hospitalization->FechaIngreso)->format('d/m/Y') }}</td><td>{{ $hospitalization->patient_name ?: 'Paciente sin nombre' }}</td><td>{{ $hospitalization->dni ?: '—' }}</td><td class="status">{{ empty($hospitalization->FechaEgreso) || $hospitalization->FechaEgreso === '0000-00-00' ? 'Hospitalizado' : 'Egresado' }}</td><td>{{ $hospitalization->visitor_name ?: 'Sin acompa&ntilde;ante' }}</td><td>{{ $hospitalization->visitor_phone ?: '—' }} {{ $hospitalization->visitor_notes ? '· '.$hospitalization->visitor_notes : '' }}</td></tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:14px;color:#9aa4ab">No hay pacientes hospitalizados en ese rango.</td></tr>
        @endforelse
    </tbody></table>

    <div class="page-break"></div>
    <h2>Ingresos y salidas registrados</h2>
    <p class="section-note">Movimientos reales de Porter&iacute;a durante el rango seleccionado.</p>
    <table class="detail"><thead><tr><th>Fecha / hora</th><th>Persona</th><th>DNI</th><th>Tipo</th><th>Movimiento</th><th>Punto de acceso</th><th>Motivo</th></tr></thead><tbody>
        @forelse($accessEntries as $entry)
        <tr><td>{{ \Illuminate\Support\Carbon::parse($entry->registered_at)->format('d/m/Y H:i') }}</td><td>{{ $entry->patient_name ?: 'Visitante' }}</td><td>{{ $entry->dni ?: '—' }}</td><td>{{ ucfirst($entry->person_type) }}</td><td class="status">{{ ucfirst($entry->movement) }}</td><td>{{ $entry->access_point }}</td><td>{{ $entry->reason }}</td></tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:14px;color:#9aa4ab">No hay ingresos ni salidas registrados en ese rango.</td></tr>
        @endforelse
    </tbody></table>
</body>
</html>
