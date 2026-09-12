@extends('layouts.app')

@section('title', 'Request account deletion - Delight')

@section('content')
    <div class="w-full max-w-xl mx-auto py-8">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6 sm:p-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Request account deletion</h1>

            <p class="mt-4 text-gray-700 dark:text-gray-300 leading-relaxed">
                Use this form to begin deleting your Delight account and associated reading history. You do not need the
                Android app or an active Delight web session.
            </p>

            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                We will email a confirmation link to verify that you control the account address. Confirmation sends your
                request to Delight support for manual review; it does not immediately delete your data.
            </p>

            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                Verified requests are normally completed within 30 days. We may retain limited information when reasonably
                necessary for legal, security, fraud-prevention, or record-keeping obligations.
            </p>

            @if (session('account_deletion_status'))
                <div role="status"
                    class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
                    {{ session('account_deletion_status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('account-deletion.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                        Delight account email
                    </label>
                    <input id="email" name="email" type="email" inputmode="email" autocomplete="email" required
                        value="{{ old('email') }}"
                        aria-describedby="email-help @error('email') email-error @enderror"
                        @error('email') aria-invalid="true" @enderror
                        class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <p id="email-help" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        We use this address only to find the account and send the verification link.
                    </p>
                    @error('email')
                        <p id="email-error" role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="inline-flex min-h-11 w-full sm:w-auto items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800">
                    Send confirmation link
                </button>
            </form>

            <p class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                Need help? Email
                <a class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                    href="mailto:{{ config('mail.support_address') }}">
                    {{ config('mail.support_address') }}
                </a>.
            </p>
        </div>
    </div>
@endsection
