<template>
    <div class="flex h-dvh [--reader-header-height:4rem]">
        <aside
            class="hidden shrink-0 flex-col border-r border-gray-200/70 overflow-hidden bg-white pt-[calc(var(--reader-header-height)+0.75rem)] transition-[width] motion-reduce:transition-none dark:border-gray-700/70 dark:bg-gray-800 lg:flex"
            :class="collapsed ? 'w-16' : 'w-64'"
        >
            <nav id="reader-sidebar-navigation" :aria-label="m.navigation" class="flex flex-col gap-1 px-2">
                <component
                    :is="linkComponent(link)"
                    v-for="link in links"
                    :key="link.key"
                    :href="link.href"
                    :title="m[link.key]"
                    class="flex min-h-12 items-center gap-3 rounded-lg px-3 active:bg-primary-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:active:bg-gray-600"
                    :aria-current="isCurrent(link) ? 'page' : undefined"
                    :class="[
                        isCurrent(link)
                            ? 'bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-900/40 dark:text-primary-300 dark:hover:bg-primary-900/60'
                            : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700',
                    ]"
                    ><svg
                        class="h-6 w-6 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle v-if="link.key === 'achievements'" cx="12" cy="8" r="6" />
                        <path :d="link.icon" /></svg
                    ><span class="whitespace-nowrap" :class="collapsed ? 'sr-only' : ''">{{ m[link.key] }}</span></component
                >
            </nav>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col lg:pt-[var(--reader-header-height)]">
            <header
                class="relative z-stack-nav border-b border-gray-200/70 bg-white dark:border-gray-700/70 dark:bg-gray-800 lg:fixed lg:inset-x-0 lg:top-0 lg:h-[var(--reader-header-height)]"
            >
                <div class="flex items-center justify-between gap-3 pl-[calc(1rem+env(safe-area-inset-left,0px))] pr-[calc(1rem+env(safe-area-inset-right,0px))] pt-[calc(0.75rem+env(safe-area-inset-top,0px))] pb-3 lg:h-full lg:pl-0 lg:pr-5 lg:py-0">
                    <div class="flex min-w-0 items-center">
                        <div class="shrink-0 overflow-hidden transition-[width] motion-reduce:transition-none" :class="collapsed ? 'lg:w-16' : 'lg:w-64'">
                            <Link :href="reader.urls.dashboard" :aria-label="reader.appName" class="flex items-center gap-3 font-semibold text-gray-900 dark:text-white lg:gap-0">
                                <img :src="reader.logoUrl" alt="" class="h-8 w-8 shrink-0 lg:mx-4" />
                                <span class="whitespace-nowrap text-lg sm:text-xl" :class="collapsed ? 'lg:hidden' : ''">{{ reader.appName }}</span>
                            </Link>
                        </div>
                        <button
                            type="button"
                            class="ml-2 hidden h-11 w-11 shrink-0 lg:inline-flex items-center justify-center rounded-lg text-gray-600 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-300 dark:hover:bg-gray-700"
                            :aria-expanded="!collapsed"
                            :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                            :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                            aria-controls="reader-sidebar-navigation"
                            @click="
                                userToggled = true;
                                collapsed = !collapsed;
                            "
                        >
                            <svg class="h-5 w-5" aria-hidden="true" fill="none" viewBox="0 0 24 24">
                                <path
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    :d="collapsed ? 'm9 6 6 6-6 6' : 'm15 6-6 6 6 6'"
                                />
                            </svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <Link
                            :href="reader.urls.log"
                            class="hidden rounded-full bg-accent-500 px-5 py-2 text-sm font-medium text-white hover:bg-accent-600 lg:inline-flex"
                            >{{ m.log }}</Link
                        >
                        <details ref="announcementsPanel" class="static sm:relative">
                            <summary
                                class="relative flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:text-gray-300 dark:hover:bg-gray-700"
                                :aria-label="reader.unreadCount ? `What's new, ${reader.unreadCount} unread updates` : `What's new`"
                            >
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m3 11 18-5v12L3 13v-2ZM7 14v7h4l-2-6" />
                                </svg>
                                <span v-if="reader.unreadCount" aria-hidden="true" class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-primary-600 dark:bg-primary-400" />
                            </summary>
                            <section aria-labelledby="whats-new-title" class="absolute inset-x-4 top-full z-10 mt-2 flex max-h-[calc(100dvh-11rem-env(safe-area-inset-top,0px)-env(safe-area-inset-bottom,0px))] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-900 shadow-lg sm:left-auto sm:right-0 sm:w-80 sm:max-w-[calc(100vw-2rem)] lg:max-h-[calc(100dvh-6rem)] dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <h2 id="whats-new-title" class="shrink-0 border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-700">What's new</h2>
                                <ul v-if="reader.announcements.length" class="min-h-0 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                                    <li v-for="announcement in reader.announcements" :key="announcement.id">
                                        <a :href="announcement.url" class="flex min-h-11 gap-3 px-4 py-3 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-500 dark:hover:bg-gray-700" @click="closeNavigationMenus">
                                            <span aria-hidden="true" class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="announcement.isRead ? 'bg-transparent' : 'bg-primary-600 dark:bg-primary-400'" />
                                            <span class="min-w-0">
                                                <span v-if="!announcement.isRead" class="sr-only">Unread: </span>
                                                <span class="block text-sm" :class="announcement.isRead ? 'font-normal text-gray-600 dark:text-gray-300' : 'font-semibold'">{{ announcement.title }}</span>
                                                <time v-if="announcement.date" :datetime="announcement.date" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ announcement.dateLabel }}</time>
                                            </span>
                                        </a>
                                    </li>
                                </ul>
                                <p v-else class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">No updates yet.</p>
                                <a :href="reader.urls.updates" class="inline-flex min-h-11 shrink-0 items-center justify-center border-t border-gray-200 px-4 py-3 text-sm font-medium text-primary-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-500 dark:border-gray-700 dark:text-primary-300 dark:hover:bg-gray-700" @click="closeNavigationMenus">All updates</a>
                            </section>
                        </details>
                        <details ref="profile" class="relative">
                            <summary
                                class="flex h-10 w-10 cursor-pointer list-none items-center justify-center overflow-hidden rounded-full bg-primary-500 font-medium text-white focus-visible:outline-2 focus-visible:outline-primary-500"
                                :aria-label="m.user_menu"
                            >
                                <img
                                    v-if="reader.user.avatar"
                                    :src="reader.user.avatar"
                                    alt=""
                                    referrerpolicy="no-referrer"
                                    class="h-full w-full object-cover"
                                /><span v-else>{{ reader.user.name.slice(0, 1).toUpperCase() }}</span>
                            </summary>
                            <div
                                class="absolute right-0 z-10 mt-2 w-64 rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                            >
                                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ reader.user.name }}
                                    </p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ reader.user.email }}
                                    </p>
                                </div>
                                <div class="p-2">
                                    <component
                                        :is="key === 'settings' || key === 'feedback' ? Link : 'a'"
                                        v-for="key in accountLinks"
                                        :key="key"
                                        :href="reader.urls[key] ?? undefined"
                                        @click="closeNavigationMenus"
                                        class="block rounded-lg px-3 py-3 text-sm hover:bg-gray-100 focus-visible:outline-2 dark:text-gray-300 dark:hover:bg-gray-700"
                                        >{{ m[key] }}</component
                                    >
                                    <form :action="reader.urls.logout" method="post">
                                        <input type="hidden" name="_token" :value="reader.csrfToken" /><button type="submit"
                                            class="w-full rounded-lg px-3 py-3 text-left text-sm text-destructive-600 dark:text-destructive-400"
                                        >
                                            {{ m.logout }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </header>
            <main scroll-region class="min-h-0 flex-1 overflow-y-auto px-mobile-safe pt-4 pb-mobile-nav-safe lg:pb-6">
                <slot />
            </main>
        </div>
        <nav
            :aria-label="m.navigation"
            class="fixed bottom-mobile-nav-safe left-1/2 z-stack-nav grid h-16 w-full max-w-lg -translate-x-1/2 grid-cols-5 rounded-full border border-gray-200 bg-white/80 shadow-xl backdrop-blur-md dark:border-gray-700 dark:bg-gray-800/80 lg:hidden"
        >
            <component
                :is="linkComponent(link)"
                v-for="link in links"
                :key="link.key"
                :href="link.href"
                :aria-label="m[link.key]"
                :aria-current="isCurrent(link) ? 'page' : undefined"
                class="flex flex-col items-center justify-center gap-1 rounded-full px-1 transition-colors active:bg-gray-200/70 focus-visible:outline-2 focus-visible:outline-primary-500 dark:active:bg-gray-700/80"
                :class="[
                    isCurrent(link) ? 'bg-primary-50 dark:bg-primary-900/40' : '',
                    link.key === 'log'
                        ? 'text-accent-500 active:text-accent-700 dark:active:text-accent-300'
                        : (isCurrent(link)
                            ? 'text-primary-700 dark:text-primary-300'
                            : 'text-gray-600 active:text-gray-900 dark:text-gray-400 dark:active:text-white'),
                ]"
                ><svg
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <circle v-if="link.key === 'achievements'" cx="12" cy="8" r="6" />
                    <path :d="link.icon" />
                </svg>
                <span class="text-[10px] leading-4 font-medium">{{ link.mobileLabel }}</span>
            </component>
        </nav>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import type { ReaderPageProps } from '@/reader-types';

const page = usePage<ReaderPageProps>();
const reader = computed(() => page.props.reader);

const stopPlanHistoryRefresh = router.on('navigate', event => {
    if (event.detail.type === 'history' && event.detail.page.component === 'PlanToday') {
        router.reload();
    }
});

const m = {
    navigation: 'Main navigation',
    dashboard: 'Dashboard',
    plans: 'Plans',
    log: 'Log Reading',
    history: 'History',
    achievements: 'Achievements',
    user_menu: 'Open user menu',
    settings: 'Settings',
    recap: 'Annual Recap',
    feedback: 'Feedback',
    admin: 'Admin',
    logout: 'Sign out',
};

const collapsed = ref(false);
const userToggled = ref(false);
const profile = ref<HTMLDetailsElement | null>(null);
const announcementsPanel = ref<HTMLDetailsElement | null>(null);

const accountLinks = computed(() => {
    const keys: ('settings' | 'recap' | 'feedback' | 'admin')[] = ['settings', 'recap', 'feedback'];
    if (reader.value.urls.admin) keys.push('admin');
    return keys;
});

const links = computed<{ key: 'dashboard' | 'plans' | 'log' | 'history' | 'achievements'; mobileLabel: string; href: string; icon: string }[]>(() => [
    {
        key: 'dashboard',
        mobileLabel: 'Home',
        href: reader.value.urls.dashboard,
        icon: 'M3 15v4m6-6v6m6-4v4m6-6v6M3 11l6-5 6 5 5.5-5.5',
    },
    {
        key: 'plans',
        mobileLabel: 'Plans',
        href: reader.value.urls.plans,
        icon: 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4',
    },
    {
        key: 'log',
        mobileLabel: 'Log',
        href: reader.value.urls.log,
        icon: 'M12 7.757v8.486M7.757 12h8.486M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    },
    {
        key: 'history',
        mobileLabel: 'History',
        href: reader.value.urls.history,
        icon: 'M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 0-6.92858 2.26806-8.29409 5.5M7 9H3V5',
    },
    {
        key: 'achievements',
        mobileLabel: 'Awards',
        href: reader.value.urls.achievements,
        icon: 'M15.477 12.89 17 22l-5-3-5 3 1.523-9.11',
    },
]);

function pathname(value: string) {
    return new URL(value, 'https://reader.invalid').pathname;
}

function linkComponent(link: { key: string; href: string }) {
    if (link.key === 'plans') {
        return Link;
    }

    return ['dashboard', 'log', 'history', 'achievements'].includes(link.key) ? Link : 'a';
}

function isCurrent(link: { key: string; href: string }) {
    const current = pathname(page.url);

    if (link.key === 'plans') {
        return current === '/plans' || current.startsWith('/plans/');
    }

    return current === pathname(link.href);
}

function closeNavigationMenus() {
    for (const menu of [profile.value, announcementsPanel.value]) if (menu) menu.open = false;
}

watch(() => page.url, closeNavigationMenus);

function closeMenus(event: MouseEvent) {
    for (const menu of [profile.value, announcementsPanel.value]) if (menu && !(event.target instanceof Node && menu.contains(event.target))) menu.open = false;
}

function escapeMenus(event: KeyboardEvent) {
    if (event.key !== 'Escape') return;
    for (const menu of [profile.value, announcementsPanel.value])
        if (menu?.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
}

function syncWidth() {
    if (!userToggled.value) collapsed.value = !window.matchMedia('(min-width: 1280px)').matches;
}

onMounted(() => {
    syncWidth();
    window.addEventListener('resize', syncWidth);
    document.addEventListener('click', closeMenus);
    document.addEventListener('keydown', escapeMenus);
});

onBeforeUnmount(() => {
    stopPlanHistoryRefresh();
    window.removeEventListener('resize', syncWidth);
    document.removeEventListener('click', closeMenus);
    document.removeEventListener('keydown', escapeMenus);
});
</script>
