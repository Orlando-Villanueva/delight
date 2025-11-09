{{-- Dashboard Page Container Partial --}}
{{-- This partial is loaded via HTMX navigation and follows the ui-prototype layout --}}

<title>Dashboard - {{ config('app.name') }}</title>

<!-- Main Dashboard Content Area (Full Width) -->
<div class="w-full p-4 lg:p-6 pb-20 lg:pb-6">
    <div id="main-content" class="h-full">
        @include('partials.dashboard-content', compact('hasReadToday', 'streakState', 'streakStateClasses', 'streakMessage', 'stats', 'weeklyGoal', 'weeklyJourney'))
    </div>
</div>
