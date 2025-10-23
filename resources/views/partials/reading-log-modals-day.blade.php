@props(['logsForDay'])

@foreach ($logsForDay as $log)
    @php
        $allLogs = $log->all_logs ?? collect([$log]);
    @endphp
    <x-modals.edit-reading-note :log="$log" />
    @if ($allLogs->count() > 1)
        <x-modals.delete-chapter-selection :log="$log" />
    @else
        <x-modals.delete-reading-confirmation :log="$log" />
    @endif
@endforeach
