@extends('layouts.authenticated')

@section('page-title', 'Log Reading')
@section('page-subtitle', 'Record your Bible reading progress')

@section('content')
<!-- Full-width Content when no sidebar is defined -->
<div class="flex-1 p-4 xl:p-6 pb-5 md:pb-20 lg:pb-6">
    <div id="main-content" class="h-full">
        <div class="max-w-2xl mx-auto sm:px-20 lg:px-32">
            @include('partials.reading-log-form', compact('books', 'errors', 'allowYesterday', 'hasReadYesterday', 'currentStreak', 'hasReadToday', 'recentBooks'))
        </div>
    </div>
</div>
@endsection