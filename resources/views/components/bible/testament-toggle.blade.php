@props([
    'id' => 'testament-toggle',
    'showDeuterocanonical' => false,
    'variant' => 'responsive',
])

<div {{ $attributes->merge(['class' => 'relative w-full sm:w-auto']) }}
    id="{{ $id }}">
    @if ($variant !== 'desktop')
        <fieldset class="min-w-0 sm:hidden">
            <legend class="sr-only">Bible section</legend>
            <div @class([
                'grid gap-1 rounded-lg border border-gray-300 bg-gray-100 p-1 dark:border-gray-600 dark:bg-gray-900',
                'grid-cols-1' => $showDeuterocanonical,
                'grid-cols-[repeat(auto-fit,minmax(min(100%,7rem),1fr))]' => ! $showDeuterocanonical,
            ])>
                @foreach (['Old' => 'Old Testament', 'New' => 'New Testament'] + ($showDeuterocanonical ? ['Deuterocanonical' => 'Deuterocanonical'] : []) as $section => $label)
                    <div class="min-w-0">
                        <input type="radio" id="{{ $id }}-mobile-{{ strtolower($section) }}"
                            name="{{ $id }}-mobile-section" value="{{ $section }}"
                            x-bind:checked="activeTestament === '{{ $section }}'"
                            x-on:change="setTestament($event.target.value)"
                            class="peer sr-only">
                        <label for="{{ $id }}-mobile-{{ strtolower($section) }}"
                            class="flex min-h-11 cursor-pointer items-center justify-center rounded border border-transparent px-2 py-2 text-center text-sm font-medium leading-normal wrap-anywhere text-gray-700 transition-colors hover:bg-gray-200 peer-checked:border-primary-500 peer-checked:bg-white peer-checked:hover:bg-white peer-checked:text-primary-700 peer-checked:shadow-sm peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-600 dark:text-gray-300 dark:hover:bg-gray-800 dark:peer-checked:border-primary-400 dark:peer-checked:bg-gray-700 dark:peer-checked:hover:bg-gray-700 dark:peer-checked:text-primary-300 dark:peer-focus-visible:outline-primary-400">
                            {{ $label }}
                        </label>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endif

    @if ($variant !== 'mobile')
        <div class="hidden gap-1 border-b border-gray-200 dark:border-gray-700 sm:flex" role="tablist" aria-label="Bible section">
            <button type="button"
                x-on:click="setTestament('Old')"
                role="tab"
                x-bind:aria-selected="activeTestament === 'Old'"
                :class="{ 'border-primary-500 text-primary-600 dark:text-primary-400': activeTestament === 'Old', 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200': activeTestament !== 'Old' }"
                class="-mb-px border-b-2 px-3 py-2 text-sm font-medium leading-[1.5] transition-colors"
        >
            Old Testament
            </button>
            @if ($showDeuterocanonical)
                <button type="button"
                    x-on:click="setTestament('Deuterocanonical')"
                    role="tab"
                    x-bind:aria-selected="activeTestament === 'Deuterocanonical'"
                    :class="{ 'border-primary-500 text-primary-600 dark:text-primary-400': activeTestament === 'Deuterocanonical', 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200': activeTestament !== 'Deuterocanonical' }"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium leading-[1.5] transition-colors"
            >
                Deuterocanonical
                </button>
            @endif
            <button type="button"
                x-on:click="setTestament('New')"
                role="tab"
                x-bind:aria-selected="activeTestament === 'New'"
                :class="{ 'border-primary-500 text-primary-600 dark:text-primary-400': activeTestament === 'New', 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200': activeTestament !== 'New' }"
                class="-mb-px border-b-2 px-3 py-2 text-sm font-medium leading-[1.5] transition-colors"
        >
            New Testament
            </button>
        </div>
    @endif
</div>
