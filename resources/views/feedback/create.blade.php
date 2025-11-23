@extends('layouts.authenticated')

@section('page-title', 'Send Feedback')
@section('page-subtitle', 'Help us improve Delight')

@section('content')
<div class="flex-1 p-4 xl:p-6 pb-5 md:pb-20 lg:pb-6">
    <div class="max-w-2xl mx-auto sm:px-20 lg:px-32">
        @include('partials.feedback-form')
    </div>
</div>
@endsection
