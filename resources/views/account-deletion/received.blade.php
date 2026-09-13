@extends('layouts.app')

@section('title', 'Account deletion request received - Delight')

@section('content')
    <div class="w-full max-w-xl mx-auto py-8">
        <div
            class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6 sm:p-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Request received</h1>
            <p role="status" class="mt-4 text-gray-700 dark:text-gray-300 leading-relaxed">
                Delight support received your verified account deletion request. We normally complete requests within 30 days
                and will contact you if additional information is required.
            </p>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Your account has not been deleted automatically.
            </p>
        </div>
    </div>
@endsection
