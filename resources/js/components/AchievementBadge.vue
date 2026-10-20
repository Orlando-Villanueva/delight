<template>
    <span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full" :class="[sizes[size], locked ? 'grayscale opacity-55' : '']">
        <img :src="`${baseUrl}/${asset}`" alt="" class="h-full w-full max-w-none object-cover" :style="{ transform: `translate(${crop.x}%, ${crop.y}%) scale(${crop.scale ?? 1.15})` }">
    </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ icon: string; baseUrl: string; size?: 'xs' | 'sm' | 'xl'; locked?: boolean }>(), { size: 'sm', locked: false });

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

// The source medallions have different centers within their square PNG canvases.
const crops: Record<string, { x: number; y: number; scale?: number }> = {
    'badge-first-reading.png': { x: 1, y: -3 },
    'badge-calendar.png': { x: 2, y: -3 },
    'badge-book-completed.png': { x: 4, y: -3, scale: 1.3 },
    'badge-library.png': { x: 4, y: -3 },
    'badge-progress.png': { x: 3.5, y: 3.5 },
    'badge-streak.png': { x: 0.5, y: 2.5 },
    'badge-target.png': { x: 2, y: 2.5 },
    'badge-trophy.png': { x: 4.5, y: 2.5 },
};
const asset = computed(() => assets[props.icon] ?? assets.trophy!);
const crop = computed(() => crops[asset.value]!);
</script>
