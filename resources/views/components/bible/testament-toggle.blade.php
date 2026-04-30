@props([
    'id' => 'testament-toggle',
    'showDeuterocanonical' => false,
])

<div {{ $attributes->merge(['class' => 'relative w-full sm:w-auto']) }}
    id="{{ $id }}">
    <div class="sm:hidden">
        <label for="{{ $id }}-mobile-select" class="sr-only">Bible section</label>
        <select id="{{ $id }}-mobile-select"
            x-model="activeTestament"
            x-on:change="setTestament($event.target.value)"
            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition-colors focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:focus:border-primary-400 dark:focus:ring-primary-400">
            <option value="Old">Old Testament</option>
            @if ($showDeuterocanonical)
                <option value="Deuterocanonical">Deuterocanonical</option>
            @endif
            <option value="New">New Testament</option>
        </select>
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
