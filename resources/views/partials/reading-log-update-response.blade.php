@props(['primaryDate', 'dayResponses' => [], 'userHasLogs' => true])

@php
    $primaryLogs = $dayResponses[$primaryDate] ?? null;
@endphp

@if ($primaryLogs && $primaryLogs->isNotEmpty())
    @include('partials.reading-log-day', [
        'date' => $primaryDate,
        'logsForDay' => $primaryLogs,
    ])
@endif

@foreach ($dayResponses as $date => $logsForDay)
    @continue($date === $primaryDate)

    @if ($logsForDay && $logsForDay->isNotEmpty())
        @include('partials.reading-log-day', [
            'date' => $date,
            'logsForDay' => $logsForDay,
            'swapMethod' => 'outerHTML',
        ])
    @else
        <li id="reading-day-{{ $date }}" hx-swap-oob="outerHTML"></li>
    @endif
@endforeach

@foreach ($dayResponses as $date => $logsForDay)
    @if ($logsForDay && $logsForDay->isNotEmpty())
        <div id="reading-log-modals-{{ $date }}" hx-swap-oob="outerHTML">
            @include('partials.reading-log-modals-day', ['logsForDay' => $logsForDay])
        </div>
    @else
        <div id="reading-log-modals-{{ $date }}" hx-swap-oob="outerHTML"></div>
    @endif
@endforeach

@unless ($userHasLogs)
    <div id="reading-log-list-container" hx-swap-oob="innerHTML">
        @include('partials.reading-log-list', ['logs' => collect()])
    </div>
    <div id="reading-log-modals" hx-swap-oob="outerHTML"></div>
@endunless
