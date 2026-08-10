<tr data-row-id="{{ $schedule->id }}"
    data-professional-id="{{ $schedule->professional?->id }}"
    data-service-id="{{ $schedule->service_id }}"
    data-service-area-id="{{ $schedule->service_area_id }}"
    data-work-shift-id="{{ $schedule->work_shift_id }}"
    data-scheduled-date="{{ $schedule->scheduled_date->format('Y-m-d') }}"
    data-notes="{{ $schedule->notes }}">
    <td>{{ $schedule->scheduled_date->format('d/m/Y') }}</td>
    <td>{{ $schedule->trabajador?->persona?->full_name ?? 'Sin nombre' }}</td>
    <td>{{ $schedule->service->name }}{{ $schedule->area ? ' - '.$schedule->area->name : '' }}</td>
    <td>{{ $schedule->shift->abbreviation ?: $schedule->shift->name }} ({{ $schedule->shift->starts_at?->format('H:i') }}–{{ $schedule->shift->ends_at?->format('H:i') }})</td>
    <td class="row-actions">
        <button type="button" class="row-action" data-edit-schedule>Editar</button>
        <form method="POST" action="{{ route('admin.professionals.schedule.destroy', $schedule) }}" class="ajax-form confirm-delete-form" data-confirm-message="¿Eliminar este turno?" data-list-container="#schedulesTableBody">@csrf @method('DELETE')<button type="submit" class="noshow-small">Eliminar</button></form>
    </td>
</tr>
