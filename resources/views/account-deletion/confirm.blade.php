@extends('layouts.app')

@section('title', 'Confirm account deletion request - Delight')

@section('content')
    <div class="w-full max-w-xl mx-auto py-8">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6 sm:p-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Confirm your deletion request</h1>

            @if ($isAlreadyConfirmed)
                <p role="status" class="mt-4 text-gray-700 dark:text-gray-300">
                    This request has already been confirmed. Delight support will contact you if additional verification is needed.
                </p>
            @else
                <p class="mt-4 text-gray-700 dark:text-gray-300 leading-relaxed">
                    Confirm that you want Delight support to review deletion of your account and associated reading history.
                    This action sends a verified request; it does not immediately delete any data.
                </p>

                @if ($errors->has('confirmation'))
                    <div role="alert"
                        class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                        {{ $errors->first('confirmation') }}
                    </div>
                @endif

                <form method="POST" action="{{ $confirmationUrl }}" class="mt-6">
                    @csrf
                    <button type="submit"
                        class="inline-flex min-h-11 w-full sm:w-auto items-center justify-center rounded-lg bg-red-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-300 dark:focus:ring-red-900">
                        Confirm deletion request
                    </button>
                </form>
            @endif

            <p class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                If you did not initiate this request, close this page. Your account remains unchanged.
            </p>
        </div>
    </div>
@endsection
