@props(['logs', 'modalsOutOfBand' => false, 'swapMethod' => 'outerHTML'])

@php
    $modalGroups = $logs instanceof \Illuminate\Pagination\AbstractPaginator ? collect($logs->items()) : collect($logs);

    $modalGroups = $modalGroups->mapWithKeys(function ($logsForDay, $key) {
        $firstLog = $logsForDay->first();
        $dateKey =
            is_string($key) && $key !== ''
                ? $key
                : ($firstLog
                    ? $firstLog->date_read->format('Y-m-d')
                    : uniqid('reading-day-'));

        return [$dateKey => $logsForDay];
    });
@endphp

@if (!$modalsOutOfBand)
    <div id="reading-log-modals">
        @foreach ($modalGroups as $date => $logsForDay)
            <div id="reading-log-modals-{{ $date }}">
                @include('partials.reading-log-modals-day', compact('logsForDay'))
            </div>
        @endforeach
    </div>
@else
    @if ($swapMethod === 'beforeend')
        <div id="reading-log-modals" hx-swap-oob="beforeend">
            @foreach ($modalGroups as $date => $logsForDay)
                <div id="reading-log-modals-{{ $date }}">
                    @include('partials.reading-log-modals-day', compact('logsForDay'))
                </div>
            @endforeach
        </div>
    @elseif ($swapMethod === 'outerHTML')
        <div id="reading-log-modals" hx-swap-oob="outerHTML">
            @foreach ($modalGroups as $date => $logsForDay)
                <div id="reading-log-modals-{{ $date }}">
                    @include('partials.reading-log-modals-day', compact('logsForDay'))
                </div>
            @endforeach
        </div>
    @else
        @foreach ($modalGroups as $date => $logsForDay)
            <div id="reading-log-modals-{{ $date }}" hx-swap-oob="{{ $swapMethod }}">
                @include('partials.reading-log-modals-day', compact('logsForDay'))
            </div>
        @endforeach
    @endif
@endif
