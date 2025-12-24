<div class="py-1 dark:bg-gray-900" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
    <div
        class="px-4 py-2 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50">
        <span
            class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notifications</span>
        <!-- Optional: Mark all read button here -->
    </div>

    <div class="max-h-80 overflow-y-auto">
        @forelse($announcements as $announcement)
            @php
                $isUnread = in_array($announcement->id, $unreadIds);
            @endphp
            <a href="{{ route('announcements.show', $announcement->slug) }}"
                class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-50 dark:border-gray-800 last:border-0 group"
                role="menuitem">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 mt-1">
                        @if($announcement->type === 'success')
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        @elseif($announcement->type === 'warning')
                            <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                        @elseif($isUnread)
                            <div class="w-2 h-2 rounded-full bg-blue-600 dark:bg-blue-500"></div>
                        @else
                            <div class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                        @endif
                    </div>
                    <div>
                        <p
                            class="text-sm {{ $isUnread ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400' }}">
                            {{ $announcement->title }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            {{ $announcement->starts_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            </a>
        @empty
            <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                No notifications
            </div>
        @endforelse
    </div>

    <div class="bg-gray-50 dark:bg-gray-800/50 p-2 text-center border-t border-gray-100 dark:border-gray-800">
        <a href="{{ route('announcements.index') }}"
            class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
            View all updates
        </a>
    </div>
</div>