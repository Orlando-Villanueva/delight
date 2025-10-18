@props([
    'logs',
    'modalsOutOfBand' => false,
    'swapMethod' => 'outerHTML',
])

<div id="reading-log-modals" @if($modalsOutOfBand) hx-swap-oob="{{ $swapMethod }}" @endif>
    @foreach ($logs as $logsForDay)
        @foreach ($logsForDay as $log)
            @php
                $allLogs = $log->all_logs ?? collect([$log]);
            @endphp
            @if ($allLogs->count() > 1)
                <x-modals.delete-chapter-selection :log="$log" />
            @else
                <x-modals.delete-reading-confirmation :log="$log" />
            @endif
        @endforeach
    @endforeach
</div>
