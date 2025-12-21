@extends('layouts.authenticated')

@section('page-title', 'Send Feedback')
@section('page-subtitle', 'Help us improve Delight')

@section('content')
    @fragment('page-content')
        <div class="flex-1 p-4 xl:p-6 pb-5 md:pb-20 lg:pb-6">
            <div class="max-w-2xl mx-auto sm:px-20 lg:px-32">
                @include('partials.feedback-form')
            </div>
        </div>
    @endfragment

    {{-- For successful submissions, we provide the success message as a fragment --}}
    @if(isset($success) && $success)
        @fragment('success-message')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700 text-center">
                <div
                    class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 p-2 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-500 dark:text-green-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Thank You!</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Your feedback has been sent. We appreciate your input.
                </p>
                <a href="{{ route('dashboard') }}"
                    class="text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 inline-flex items-center">
                    Return to Dashboard
                </a>
            </div>
        @endfragment
    @endif
@endsection