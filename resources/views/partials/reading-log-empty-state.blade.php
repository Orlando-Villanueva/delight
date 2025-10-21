{{-- Reading Log Empty State --}}
<div class="text-center py-12 pb-20 lg:pb-12">
    <div class="w-16 h-16 mx-auto mb-4 text-gray-400">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
            </path>
        </svg>
    </div>

    <h3 class="text-lg font-medium text-gray-900 mb-2">No reading logs found</h3>

    <p class="text-gray-600 mb-6">You haven't logged any Bible readings yet. Start building your reading habit!</p>
    <button type="button" hx-get="{{ route('logs.create') }}" hx-target="#page-container" hx-swap="innerHTML"
        hx-push-url="true"
        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-accent-500 px-5 py-2.5 text-sm font-medium text-white transition-colors duration-150 hover:bg-accent-600 focus:outline-none focus:ring-4 focus:ring-accent-300 dark:bg-accent-600 dark:hover:bg-accent-700 dark:focus:ring-accent-800">
        📖 Log Your First Reading
    </button>
</div>
