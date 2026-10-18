<template>
    <div class="flex h-dvh">
        <aside
            class="hidden shrink-0 flex-col border-r border-gray-200 overflow-hidden bg-white pt-20 transition-[width] motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-800 lg:flex"
            :class="collapsed ? 'w-16' : 'w-64'"
        >
            <div class="flex h-14 items-center px-2" :class="collapsed ? 'justify-center' : 'justify-end'">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-gray-600 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-300 dark:hover:bg-gray-700"
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
            <nav id="reader-sidebar-navigation" :aria-label="m.navigation" class="flex flex-col gap-1 px-2">
                <a
                    v-for="link in links"
                    :key="link.key"
                    :href="link.href"
                    :title="m[link.key]"
                    class="flex min-h-12 items-center rounded-lg text-gray-900 hover:bg-primary-50 active:bg-primary-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-white dark:hover:bg-gray-700 dark:active:bg-gray-600"
                    :class="collapsed ? 'justify-center px-0' : 'gap-3 px-3'"
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
                    ><span class="whitespace-nowrap" :class="collapsed ? 'sr-only' : ''">{{ m[link.key] }}</span></a
                >
            </nav>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col lg:pt-16">
            <header
                class="z-stack-nav border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 lg:fixed lg:inset-x-0 lg:top-0"
            >
                <div class="flex items-center justify-between gap-3 px-mobile-safe pt-mobile-safe pb-3 lg:px-5 lg:py-3">
                    <a :href="reader.urls.dashboard" class="flex items-center gap-3 font-semibold text-gray-900 dark:text-white"
                        ><img :src="reader.logoUrl" :alt="reader.appName + ' Logo'" class="h-8 w-8" /><span class="text-lg sm:text-xl">{{
                            reader.appName
                        }}</span></a
                    >
                    <div class="flex items-center gap-3">
                        <a
                            :href="reader.urls.log"
                            class="hidden rounded-full bg-accent-500 px-5 py-2 text-sm font-medium text-white hover:bg-accent-600 lg:inline-flex"
                            >{{ m.log }}</a
                        >
                        <details
                            ref="notifications"
                            class="relative"
                            @toggle="
                                (event) => {
                                    if (event.target.open) loadNotifications();
                                }
                            "
                        >
                            <summary
                                class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-full text-gray-500 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-300"
                                :aria-label="m.notifications"
                            >
                                <svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg
                                ><span v-if="reader.unreadCount" class="absolute right-2 top-1 h-2 w-2 rounded-full bg-red-500" />
                            </summary>
                            <div
                                class="absolute right-0 z-10 mt-2 w-72 max-w-[85vw] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                            >
                                <p v-if="notificationError" class="p-4" role="alert">
                                    {{ m.navigation_error }}
                                    <button class="underline" @click="loadNotifications">
                                        {{ m.retry }}
                                    </button>
                                </p>
                                <div v-else-if="notificationHtml" v-html="notificationHtml" />
                                <p v-else class="p-4" role="status">
                                    {{ m.loading }}
                                </p>
                            </div>
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
                                    <a
                                        v-for="key in ['settings', 'recap', 'feedback', ...(reader.urls.admin ? ['admin'] : [])]"
                                        :key="key"
                                        :href="reader.urls[key]"
                                        class="block rounded-lg px-3 py-3 text-sm hover:bg-gray-100 focus-visible:outline-2 dark:text-gray-300 dark:hover:bg-gray-700"
                                        >{{ m[key] }}</a
                                    >
                                    <form :action="reader.urls.logout" method="post">
                                        <input type="hidden" name="_token" :value="reader.csrfToken" /><button
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
            <a
                v-for="link in links"
                :key="link.key"
                :href="link.href"
                :aria-label="m[link.key]"
                class="flex flex-col items-center justify-center gap-1 rounded-full px-1 transition-colors active:bg-gray-200/70 focus-visible:outline-2 focus-visible:outline-primary-500 dark:active:bg-gray-700/80"
                :class="
                    link.key === 'log'
                        ? 'text-accent-500 active:text-accent-700 dark:active:text-accent-300'
                        : 'text-gray-600 active:text-gray-900 dark:text-gray-400 dark:active:text-white'
                "
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
            </a>
        </nav>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const reader = computed(() => page.props.reader);

const m = {
    navigation: 'Main navigation',
    dashboard: 'Dashboard',
    plans: 'Plans',
    log: 'Log Reading',
    history: 'History',
    achievements: 'Achievements',
    notifications: 'View notifications',
    user_menu: 'Open user menu',
    settings: 'Settings',
    recap: 'Annual Recap',
    feedback: 'Feedback',
    admin: 'Admin',
    logout: 'Sign out',
    navigation_error: 'Notifications could not be loaded.',
    retry: 'Try again',
    loading: 'Loading…',
};

const collapsed = ref(false);
const userToggled = ref(false);
const profile = ref(null);
const notifications = ref(null);
const notificationHtml = ref('');
const notificationError = ref(false);

const links = computed(() => [
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

async function loadNotifications() {
    notificationError.value = false;
    try {
        const response = await fetch(reader.value.urls.notifications, {
            headers: { Accept: 'text/html' },
            credentials: 'same-origin',
        });
        if (!response.ok || response.redirected) throw new Error();
        notificationHtml.value = await response.text();
    } catch {
        notificationError.value = true;
    }
}

function closeMenus(event) {
    for (const menu of [profile.value, notifications.value]) if (menu && !menu.contains(event.target)) menu.open = false;
}

function escapeMenus(event) {
    if (event.key !== 'Escape') return;
    for (const menu of [profile.value, notifications.value])
        if (menu?.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
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
    window.removeEventListener('resize', syncWidth);
    document.removeEventListener('click', closeMenus);
    document.removeEventListener('keydown', escapeMenus);
});
</script>
