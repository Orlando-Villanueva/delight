@extends('layouts.app')

@section('content')
    <div class="w-full max-w-6xl mx-auto py-10 space-y-10">
        <div class="space-y-2 text-center">
            <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">Component Demo</p>
            <h1 class="text-3xl font-semibold">Weekly Journey States</h1>
            <p class="text-base text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Explore the various UI states of the <code
                    class="px-1 bg-gray-100 dark:bg-gray-800 rounded">x-ui.weekly-journey-card</code>
                component. Each scenario below feeds different props into the component so you can review empty, midweek,
                catch-up, and perfect-week experiences in one place.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-10 md:grid-cols-2 xl:grid-cols-3 md:gap-y-12 xl:gap-y-14 items-start">
            @foreach ($variants as $variant)
                @php($props = $variant['props'])
                <div class="flex flex-col gap-4 h-full">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Scenario {{ $loop->iteration }}
                        </p>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $variant['title'] }}
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ $variant['description'] }}
                        </p>
                    </div>

                    <div class="flex-1">
                        <x-ui.weekly-journey-card :currentProgress="$props['currentProgress']" :days="$props['days']" :weekRangeText="$props['weekRangeText']" :weeklyTarget="$props['weeklyTarget']"
                            :ctaEnabled="$props['ctaEnabled']" :ctaVisible="$props['ctaVisible']" :status="$props['status']" :journeyAltText="$props['journeyAltText']" class="h-full" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
