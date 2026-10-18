<template>
    <span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full" :class="[sizes[size], locked ? 'grayscale opacity-55' : '']">
        <img :src="`${baseUrl}/${assets[icon] ?? assets.trophy}`" alt="" class="h-full w-full max-w-none translate-x-[1%] -translate-y-[2%] scale-110 object-cover">
    </span>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ icon: string; baseUrl: string; size?: 'xs' | 'sm' | 'xl'; locked?: boolean }>(), { size: 'sm', locked: false });

const sizes = { xs: 'size-8', sm: 'size-10', xl: 'size-20' };
const assets: Record<string, string> = {
    sparkles: 'badge-first-reading.png',
    calendar: 'badge-calendar.png',
    'calendar-check': 'badge-calendar.png',
    'book-open': 'badge-book-completed.png',
    library: 'badge-library.png',
    'trending-up': 'badge-progress.png',
    flame: 'badge-streak.png',
    target: 'badge-target.png',
    chart: 'badge-progress.png',
    trophy: 'badge-trophy.png',
};
</script>
