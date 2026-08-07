<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    body{font-family:'DejaVu Sans',sans-serif;font-size:10px;color:#183052;margin:0;padding:20px}
    h1{font-size:16px;margin:0 0 2px}
    .subtitle{font-size:9px;color:#6f7d8d;margin:0 0 16px}
    table{width:100%;border-collapse:collapse}
    .metrics{margin-bottom:16px}
    .metrics td{padding:8px 10px;border:1px solid #dfe5ee;text-align:center}
    .metrics strong{display:block;font-size:14px}
    .metrics span{display:block;font-size:8px;color:#6f7d8d;text-transform:uppercase;letter-spacing:.5px}
    .detail th{background:#f5f6f3;font-size:8px;text-transform:uppercase;letter-spacing:.5px;color:#6f7d8d;padding:6px 8px;border-bottom:1px solid #dfe5ee;text-align:left}
    .detail td{padding:6px 8px;border-bottom:1px solid #edf0f2;font-size:9px}
    .footer{margin-top:14px;font-size:8px;color:#9aa4ab}
</style>
</head>
<body>
    <h1>Hospital La Merced Paita — Reporte de atención</h1>
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

    <p class="footer">Hospital La Merced Paita · Sistema de gestión hospitalaria</p>
</body>
</html>
