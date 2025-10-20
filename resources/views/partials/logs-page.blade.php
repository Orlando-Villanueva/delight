{{-- Logs Page Container Partial (No Sidebar) --}}
{{-- This partial is loaded via HTMX navigation and includes the page-container structure --}}

<title>Reading History - {{ config('app.name') }}</title>

<!-- Full-width Content when no sidebar is defined -->
<div class="flex-1 p-4 lg:p-6 lg:pb-6 container">
    <div id="main-content" class="h-full">
        @include('partials.logs-content', compact('logs'))
    </div>
</div> 