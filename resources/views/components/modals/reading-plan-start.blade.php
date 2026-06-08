@props([
    'plans',
    'hasActivePlan' => false,
])

@php
    $defaultSubscribeUrl = collect($plans)->first()['subscribeUrl'] ?? '#';
@endphp

<div id="reading-plan-start-modal" tabindex="-1" data-modal-backdrop="static" role="dialog" aria-modal="true"
    aria-labelledby="reading-plan-start-title" aria-describedby="reading-plan-start-desc"
    x-data="readingPlanStartModal()" x-init="init()"
    x-on:open-reading-plan-start.window="open($event.detail.slug)"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-stack-modal justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <script type="application/json" data-reading-plan-start-data>@json($plans)</script>

    <div class="relative p-4 w-full max-w-md max-h-full">
        <div class="relative bg-gray-50 rounded-lg shadow dark:bg-[#2f3746]">
            <button type="button"
                class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                data-modal-hide="reading-plan-start-modal">
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
                <span class="sr-only">Close modal</span>
            </button>

            <div class="p-4 md:p-5 border-b border-gray-200/80 rounded-t-lg bg-white dark:bg-[#2f3746] dark:border-white/10">
                <h3 id="reading-plan-start-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                    Start from a different passage
                </h3>
                <p id="reading-plan-start-desc" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Choose where tracking should begin<span x-show="selectedPlan"> for <span x-text="selectedPlan?.name"></span></span>.
                </p>
            </div>

            <form method="POST" action="{{ $defaultSubscribeUrl }}" hx-post="{{ $defaultSubscribeUrl }}"
                x-ref="form" x-bind:action="actionUrl" x-bind:hx-post="actionUrl" hx-target="#page-container" hx-swap="innerHTML"
                hx-disabled-elt="#reading-plan-start-actions button"
                hx-on::after-request="if (event.detail.successful) { document.body.dispatchEvent(new CustomEvent('hideModal', { detail: { id: 'reading-plan-start-modal' } })); }"
                @if ($hasActivePlan) hx-confirm="Starting this plan will pause your current active plan. Continue?" @endif>
                @csrf

                <div class="space-y-4 p-4 md:p-5 bg-gray-50 dark:bg-[#2f3746]">
                    <div>
                        <label for="reading-plan-start-day" class="block text-sm font-medium text-gray-900 dark:text-white">
                            Starting passage
                        </label>
                        <select id="reading-plan-start-day" name="start_day" x-model="selectedDay"
                            class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <template x-for="day in selectedPlan?.days ?? []" x-bind:key="day.day">
                                <option x-bind:value="day.day" x-text="day.optionLabel"></option>
                            </template>
                        </select>
                    </div>

                    <p class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-900/20 dark:text-blue-100">
                        Earlier days will be marked <span class="font-semibold">Before tracking</span> and will not count toward tracked completion.
                    </p>
                </div>

                <div id="reading-plan-start-actions" class="flex flex-col-reverse gap-3 p-4 md:p-5 border-t border-gray-200/80 rounded-b-lg bg-gray-50 dark:bg-[#2f3746] dark:border-white/10 sm:flex-row sm:justify-end">
                    <button type="button" data-modal-hide="reading-plan-start-modal"
                        class="w-full sm:w-auto py-2.5 px-5 text-sm font-medium text-gray-900 bg-gray-200 rounded-lg border border-gray-300 hover:bg-gray-300 hover:text-gray-900 focus-visible:outline-none focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:bg-gray-600 dark:text-gray-100 dark:border-gray-500 dark:hover:bg-gray-700 dark:hover:text-white dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-gray-900">
                        Cancel
                    </button>
                    <button type="submit" x-bind:disabled="! selectedPlan || selectedDayNumber <= 0"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 disabled:bg-primary-300 disabled:text-white/70 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 rounded-lg">
                        <span x-text="buttonLabel">Start tracking</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function readingPlanStartModal() {
        return {
            plans: {},
            selectedSlug: null,
            selectedDay: null,
            init() {
                const dataElement = this.$root.querySelector('[data-reading-plan-start-data]');
                this.plans = JSON.parse(dataElement?.textContent || '{}');
            },
            open(slug) {
                this.selectedSlug = slug;
                this.selectedDay = this.selectedPlan?.firstDay ?? null;

                this.$nextTick(() => {
                    if (typeof window.htmx?.process === 'function') {
                        window.htmx.process(this.$refs.form);
                    }
                });
            },
            get selectedPlan() {
                return this.plans[this.selectedSlug] ?? null;
            },
            get selectedDayNumber() {
                return Number(this.selectedDay ?? this.selectedPlan?.firstDay ?? 0);
            },
            get actionUrl() {
                return this.selectedPlan?.subscribeUrl ?? '#';
            },
            get buttonLabel() {
                return this.selectedDayNumber > 0
                    ? `Start tracking from Day ${this.selectedDayNumber}`
                    : 'Start tracking';
            },
        };
    }
</script>
