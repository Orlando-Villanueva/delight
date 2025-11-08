@extends('layouts.authenticated')

@section('page-title', 'Dashboard')
@section('page-subtitle', 'Track your Bible reading progress')

@section('content')
<!-- Main Dashboard Content Area (Full Width) -->
<div class="w-full p-4 lg:p-6 pb-20 lg:pb-6">
    <div id="main-content" class="h-full">
        @include('partials.dashboard-content', compact('hasReadToday', 'streakState', 'streakStateClasses', 'streakMessage', 'stats', 'weeklyGoal', 'weeklyJourney'))
    </div>
</div>
@endsection
