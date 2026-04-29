@props([
    'id' => 'testament-toggle',
    'showDeuterocanonical' => false,
])

<div {{ $attributes->merge(['class' => 'relative w-full sm:w-auto']) }}
    id="{{ $id }}"
    x-data="{ sectionMenuOpen: false }"
    x-on:keydown.escape.window="sectionMenuOpen = false">
    <div class="sm:hidden">
        <button type="button"
            x-on:click="sectionMenuOpen = !sectionMenuOpen"
            x-bind:aria-expanded="sectionMenuOpen"
            aria-haspopup="listbox"
            class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm font-medium text-gray-800 shadow-sm transition-colors hover:border-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:hover:border-primary-400">
            <span x-text="testamentLabel(activeTestament)"></span>
            <svg class="h-4 w-4 text-gray-500 transition-transform dark:text-gray-400"
                :class="{ 'rotate-180': sectionMenuOpen }"
                aria-hidden="true"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
            </svg>
        </button>

        <div x-show="sectionMenuOpen"
            x-cloak
            x-transition.origin.top
            x-on:click.outside="sectionMenuOpen = false"
            class="absolute left-0 right-0 z-20 mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            role="listbox">
            <button type="button"
                x-on:click="setTestament('Old'); sectionMenuOpen = false"
                x-bind:aria-selected="activeTestament === 'Old'"
                :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300': activeTestament === 'Old', 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700': activeTestament !== 'Old' }"
                class="block w-full px-3 py-2.5 text-left text-sm font-medium"
                role="option">
                Old Testament
            </button>
            @if ($showDeuterocanonical)
                <button type="button"
                    x-on:click="setTestament('Deuterocanonical'); sectionMenuOpen = false"
                    x-bind:aria-selected="activeTestament === 'Deuterocanonical'"
                    :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300': activeTestament === 'Deuterocanonical', 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700': activeTestament !== 'Deuterocanonical' }"
                    class="block w-full px-3 py-2.5 text-left text-sm font-medium"
                    role="option">
                    Deuterocanonical
                </button>
            @endif
            <button type="button"
                x-on:click="setTestament('New'); sectionMenuOpen = false"
                x-bind:aria-selected="activeTestament === 'New'"
                :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300': activeTestament === 'New', 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700': activeTestament !== 'New' }"
                class="block w-full px-3 py-2.5 text-left text-sm font-medium"
                role="option">
                New Testament
            </button>
        </div>
    </div>

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
</div>
