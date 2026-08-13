@php
    $graceMinutes = (int) config('hospital.attendance_grace_minutes', 20);
    $isLate = $a->confirmed_at?->gt($a->scheduled_at->copy()->addMinutes($graceMinutes));
    $statusLabel = match ($a->status) {
        'no_asistio' => 'Inasistencia automática',
        'confirmada' => $isLate ? 'Llegó tarde' : 'Confirmada',
        default => ucfirst(str_replace('_', ' ', $a->status)),
    };
    $entry = $a->accessLogs->firstWhere('movement', 'ingreso')?->registered_at;
    $exit = $a->accessLogs->where('movement', 'salida')->last()?->registered_at;
@endphp
<tr data-row-id="{{ $a->id }}" data-search="{{ mb_strtolower($a->patient->full_name.' '.$a->patient->dni.' '.$a->type->service->name.' '.$a->type->name) }}" data-service="{{ $a->type->service_id }}" data-status="{{ $a->status }}" data-scheduled-at="{{ $a->scheduled_at->toIso8601String() }}" data-patient-name="{{ $a->patient->full_name }}" data-service-name="{{ $a->type->service->name }}" data-entry-at="{{ $entry?->toIso8601String() }}" data-exit-at="{{ $exit?->toIso8601String() }}">
    <td><strong>{{ $a->scheduled_at->format('h:i') }} {{ $a->scheduled_at->hour < 12 ? 'a. m.' : 'p. m.' }}</strong><small>{{ $a->scheduled_at->format('d/m/Y') }}</small></td>
    <td><strong>{{ $a->patient->full_name }}</strong><small>DNI {{ $a->patient->dni }}</small></td>
    <td title="{{ $a->type->service->name }}">{{ $a->type->service->name }}</td>
    <td title="{{ $a->type->name }}">{{ $a->type->name }}</td>
    <td class="movement-date">{{ $entry?->format('d/m/Y') ?? '—' }}</td>
    <td class="movement-time">{{ $entry ? $entry->format('h:i').' '.($entry->hour < 12 ? 'a. m.' : 'p. m.') : '—' }}</td>
    <td class="movement-date">{{ $exit?->format('d/m/Y') ?? '—' }}</td>
    <td class="movement-time">{{ $exit ? $exit->format('h:i').' '.($exit->hour < 12 ? 'a. m.' : 'p. m.') : '—' }}</td>
    <td><span class="status {{ $a->status }}{{ $a->status === 'confirmada' && $isLate ? ' late' : '' }}">{{ $statusLabel }}</span></td>
    <td>
        @if($a->status === 'programada' || ($a->status === 'no_asistio' && $a->scheduled_at->isToday()))
            <form id="confirmForm{{ $a->id }}" method="POST" action="{{ route('appointments.confirm', $a) }}" class="ajax-form" data-list-container="#patientsTableBody">@csrf @method('PUT')</form>
            <button type="button" class="confirm-small" data-attendance-action="{{ $a->status === 'no_asistio' ? 'tardanza' : 'asistio' }}" data-form-target="confirmForm{{ $a->id }}" data-patient-name="{{ $a->patient->full_name }}" data-patient-meta="{{ $a->scheduled_at->format('H:i') }} · {{ $a->type->service->name }}">{{ $a->status === 'no_asistio' ? 'Registrar llegada tardía' : 'Confirmar llegada' }}</button>
        @elseif($entry && !$exit)
            <form id="checkoutForm{{ $a->id }}" method="POST" action="{{ route('appointments.checkout', $a) }}" class="ajax-form" data-list-container="#patientsTableBody">@csrf @method('PUT')</form>
            <button type="submit" form="checkoutForm{{ $a->id }}" class="noshow-small">Registrar salida</button>
        @elseif($exit)
            <span class="confirmed-label">Salida registrada</span>
        @elseif($a->status === 'confirmada' && $isLate)
            <span class="confirmed-label">◷ Llegó tarde</span>
        @elseif($a->status === 'no_asistio')
            <span class="confirmed-label">Cita perdida</span>
        @else
            <span class="confirmed-label">✓ Verificada</span>
        @endif
    </td>
</tr>
