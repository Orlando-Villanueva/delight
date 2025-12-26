<div
    class="block px-4 py-3 font-semibold text-center text-gray-700 bg-gray-50/50 dark:bg-gray-800/50 dark:text-white rounded-t-xl border-b border-gray-100 dark:border-gray-700 text-xs uppercase tracking-widest">
    Notifications
</div>

<div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto" hx-boost="true"
    hx-target="#page-container" hx-swap="innerHTML">
    @forelse($announcements as $announcement)
        @php
            $isUnread = in_array($announcement->id, $unreadIds);
        @endphp
        <a href="{{ route('announcements.show', $announcement->slug) }}"
            class="flex px-4 py-4 hover:bg-gray-100/80 dark:hover:bg-gray-700/50 transition-colors group">
            <div class="flex-shrink-0 mt-1">
                @if ($announcement->type === 'success')
                    <div class="w-2.5 h-2.5 rounded-full bg-success-500 shadow-sm shadow-success-500/50"></div>
                @elseif($announcement->type === 'warning')
                    <div class="w-2.5 h-2.5 rounded-full bg-yellow-400 shadow-sm shadow-yellow-400/50"></div>
                @elseif($isUnread)
                    <div class="w-2.5 h-2.5 rounded-full bg-primary-500 shadow-sm shadow-primary-500/50"></div>
                @else
                    <div class="w-2.5 h-2.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                @endif
            </div>
            <div class="w-full ps-3">
                <div
                    class="text-gray-600 dark:text-gray-100 text-sm mb-1.5 {{ $isUnread ? 'font-semibold text-gray-900 dark:text-white' : '' }}">
                    {{ $announcement->title }}
                </div>
                <div class="text-xs text-primary-500 dark:text-primary-400 font-medium">
                    {{ $announcement->starts_at->diffForHumans() }}
                </div>
            </div>
        </a>
    @empty
        <div class="px-4 py-8 text-center">
            <div class="text-gray-400 dark:text-gray-500 mb-2">
                <svg class="w-10 h-10 mx-auto opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">All caught up! No new notifications.</div>
        </div>
    @endforelse
</div>

<div hx-boost="true" hx-target="#page-container" hx-swap="innerHTML">
    <a href="{{ route('announcements.index') }}"
        class="block py-3 text-xs font-semibold text-center text-gray-900 bg-gray-50/50 hover:bg-gray-100/80 dark:bg-gray-800/50 dark:hover:bg-gray-700/80 dark:text-white rounded-b-xl border-t border-gray-100 dark:border-gray-700 transition-colors">
        <div class="inline-flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View All Updates
        </div>
    </a>
</div>
