@php
    $entry = $a->accessLogs->firstWhere('movement', 'ingreso')?->registered_at;
@endphp
<tr data-row-id="{{ $a->id }}" data-status="{{ $a->status }}" data-scheduled-at="{{ $a->scheduled_at->toIso8601String() }}" data-patient-name="{{ $a->patient->full_name }}" data-service-name="{{ $a->type->service->name }}" data-patient-dni="{{ $a->patient->dni }}" hidden>
    <td><strong>{{ $a->scheduled_at->format('h:i') }} {{ $a->scheduled_at->hour < 12 ? 'a. m.' : 'p. m.' }}</strong><small>{{ $a->scheduled_at->format('d/m/Y') }}</small></td>
    <td><strong>{{ $a->patient->full_name }}</strong><small>DNI {{ $a->patient->dni }}</small></td>
    <td title="{{ $a->type->service->name }}">{{ $a->type->service->name }}</td>
    <td>
        @if($a->status === 'programada' || ($a->status === 'no_asistio' && $a->scheduled_at->isToday()))
            <form id="confirmForm{{ $a->id }}" method="POST" action="{{ route('appointments.confirm', $a) }}" class="ajax-form" data-list-container="#patientsTableBody">@csrf @method('PUT')</form>
            <button type="button" class="confirm-small" data-attendance-action="asistio" data-form-target="confirmForm{{ $a->id }}" data-patient-name="{{ $a->patient->full_name }}" data-patient-meta="{{ $a->scheduled_at->format('H:i') }} · {{ $a->type->service->name }}">Registrar ingreso</button>
        @elseif($entry)
            <span class="confirmed-label">✓ Ingreso registrado</span>
        @elseif($a->status === 'no_asistio')
            <span class="confirmed-label">Cita perdida</span>
        @else
            <span class="confirmed-label">✓ Verificada</span>
        @endif
    </td>
</tr>
