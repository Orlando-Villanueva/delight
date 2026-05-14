@extends('layouts.authenticated')

@section('page-title', 'Reading History')
@section('page-subtitle', 'Review and manage past readings.')

@section('content')
    @fragment('page-content')
        <x-ui.page-shell width="list" id="main-content">
            <x-ui.page-header
                title="Reading History"
                subtitle="Review and manage past readings."
            />

            {{-- Reading Log Content Container --}}
            <div id="reading-content" class="relative">
                {{-- Loading Indicator - Only covers the logs area --}}
                <div id="loading"
                    class="htmx-indicator hidden absolute inset-0 bg-white dark:bg-gray-900 bg-opacity-90 dark:bg-opacity-90 flex items-center justify-center z-10"
                    aria-hidden="true">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
                    <span class="ml-3 text-gray-600 dark:text-gray-400">Loading readings...</span>
                </div>

                <div id="reading-log-list-container">
                    @include('partials.reading-log-list', compact('logs'))
                </div>
            </div>
        </x-ui.page-shell>
    @endfragment
@endsection
