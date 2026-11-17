<template>
    <section :aria-labelledby="titleId" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h2 :id="titleId" class="text-lg font-semibold text-gray-900 dark:text-white">{{ copy.readingCalendar }}</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ calendar.monthName }}</p>
        <div aria-hidden="true" class="mb-2 mt-4 grid grid-cols-7 gap-1 text-center text-xs text-gray-500 dark:text-gray-400"><span v-for="day in weekdays" :key="day">{{ day }}</span></div>
        <ol :aria-label="calendar.monthName" class="grid grid-cols-7 gap-1">
            <li v-for="(day, index) in calendar.days" :key="day?.date ?? index" :aria-label="day ? dayLabel(day) : undefined" :aria-hidden="day ? undefined : true" class="flex aspect-square items-center justify-center rounded-full text-xs" :class="day ? dayClass(day) : ''">
                <time v-if="day" :datetime="day.date" :aria-current="day.isToday ? 'date' : undefined">{{ day.day }}</time>
            </li>
        </ol>
        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-200 pt-3 text-center dark:border-gray-700">
            <div><dd class="font-semibold text-gray-900 dark:text-white">{{ calendar.thisMonthReadings }} / {{ calendar.daysPassedInMonth }}</dd><dt class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ copy.daysRead }}</dt></div>
            <div><dd class="font-semibold text-gray-900 dark:text-white">{{ calendar.thisMonthChapters }}</dd><dt class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ copy.chaptersLogged }}</dt></div>
        </dl>
    </section>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { CalendarDay, DashboardCalendarData } from '@/dashboard-types';
import { useReaderCopy } from '@/reader-copy';
import type { ReaderPageProps } from '@/reader-types';

defineProps<{ calendar: DashboardCalendarData }>();
const titleId = useId();
const copy = useReaderCopy();
const page = usePage<ReaderPageProps>();
const pageLocale = computed(() => page.props.reader.locale === 'fr' ? 'fr-CA' : 'en');
const weekdays = computed(() => pageLocale.value === 'fr-CA' ? ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'] : ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']);
const intensities = ['bg-success-50 text-success-950 dark:bg-success-900 dark:text-success-100', 'bg-success-100 text-success-950 dark:bg-success-800 dark:text-white', 'bg-success-200 text-success-950 dark:bg-success-700 dark:text-white', 'bg-success-300 text-success-950 dark:bg-success-600 dark:text-white', 'bg-success-400 text-success-950 dark:bg-success-500 dark:text-success-950', 'bg-success-500 text-success-950 dark:bg-success-400 dark:text-success-950', 'bg-success-600 text-white dark:bg-success-300 dark:text-success-950'];

function dayClass(day: CalendarDay) {
    const state = day.isFuture ? 'text-gray-400 dark:text-gray-500'
        : day.readingCount > 0 ? intensities[Math.min(day.readingCount, 7) - 1]
            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
    return [state, day.isToday ? 'ring-2 ring-primary-500 ring-offset-1 ring-offset-white dark:ring-primary-400 dark:ring-offset-gray-800' : ''];
}

function dayLabel(day: CalendarDay) {
    const [year, month, date] = day.date.split('-').map(Number);
    const label = new Intl.DateTimeFormat(pageLocale.value, { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(year!, month! - 1, date!));
    return `${label}${day.isToday ? `, ${copy.value.today}` : ''}: ${day.isFuture ? copy.value.futureDate : copy.value.loggedChapterCount(day.readingCount)}`;
}
</script>
