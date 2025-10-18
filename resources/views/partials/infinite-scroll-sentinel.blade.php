{{-- Infinite Scroll Sentinel Partial --}}
{{-- This partial renders the intersection observer element for infinite scroll --}}
<li id="infinite-scroll-sentinel"
    class="list-none ms-6 py-4 flex justify-center items-center"
    hx-get="{{ $logs->nextPageUrl() }}"
    hx-trigger="intersect once"
    hx-target="this"
    hx-swap="outerHTML">
    {{-- Loading indicator that shows during fetch --}}
    <div class="htmx-indicator flex items-center space-x-2 text-gray-500">
        <div class="animate-spin h-5 w-5 border-2 border-primary-600 border-t-transparent rounded-full"></div>
        <span class="text-sm">Loading more readings...</span>
    </div>
</li>
