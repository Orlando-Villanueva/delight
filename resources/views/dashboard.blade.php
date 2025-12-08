@extends('layouts.authenticated')

@section('page-title', 'Dashboard')
@section('page-subtitle', 'Track your Bible reading progress')

@section('content')
    <!-- Main Dashboard Content Area (Full Width) -->
    <div class="w-full p-4 lg:p-6 pb-20 lg:pb-6">

        <!-- Delight Rewind Banner (December Only) -->
        @if((now()->month == 12 && now()->day >= 25) || app()->environment(['local', 'staging']))
        <div class="mb-6 relative overflow-hidden rounded-2xl bg-gradient-to-r from-purple-900 to-blue-900 shadow-xl text-white">
            <div class="absolute inset-0 opacity-20">
                <svg class="h-full w-full" fill="currentColor" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <path d="M0 100 C 20 0 50 0 100 100 Z" />
                </svg>
            </div>
            <div class="relative px-6 py-8 sm:px-10 sm:py-10 flex flex-col sm:flex-row items-center justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-black tracking-tight mb-2">Delight Rewind {{ now()->year }}</h2>
                    <p class="text-blue-200 text-lg max-w-xl">Your year in Scripture has been incredible. Look back at your reading journey, streaks, and favorite moments.</p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('rewind.index') }}" class="inline-flex items-center justify-center px-8 py-3 text-base font-bold text-purple-900 bg-white rounded-full hover:bg-gray-100 transition shadow-lg transform hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Watch Recap
                    </a>
                </div>
            </div>
        </div>
        @endif

        <div id="main-content" class="h-full">
            @include(
                'partials.dashboard-content',
                compact(
                    'hasReadToday',
                    'streakState',
                    'streakStateClasses',
                    'streakMessage',
                    'streakMessageTone',
                    'stats',
                    'weeklyGoal',
                    'weeklyJourney'))
        </div>
    </div>
@endsection
