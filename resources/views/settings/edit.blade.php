@extends('layouts.authenticated')

@section('page-title', 'Settings')
@section('page-subtitle', 'Manage your account preferences')

@section('content')
    @fragment('page-content')
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('settings.update') }}"
                class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                @csrf
                @method('PATCH')

                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Deuterocanonical books</h2>
                        <p class="text-sm leading-6 text-gray-600 dark:text-gray-400">
                            Include Tobit, Judith, Wisdom, Sirach, Baruch, 1-2 Maccabees, and the Catholic-integrated
                            additions to Esther and Daniel when logging readings.
                        </p>
                    </div>

                    <label class="inline-flex cursor-pointer items-center gap-3">
                        <input type="hidden" name="include_deuterocanonical" value="0">
                        <input type="checkbox" name="include_deuterocanonical" value="1"
                            @checked(auth()->user()?->includesDeuterocanonicalBooks())
                            class="peer sr-only">
                        <span
                            class="relative h-6 w-11 rounded-full bg-gray-200 after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:bg-gray-700 dark:peer-focus:ring-primary-800 rtl:peer-checked:after:-translate-x-full"></span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ auth()->user()?->includesDeuterocanonicalBooks() ? 'Enabled' : 'Disabled' }}
                        </span>
                    </label>
                </div>

                @if (session('status'))
                    <p class="form-success">{{ session('status') }}</p>
                @endif

                <div class="flex justify-end border-t border-gray-200 pt-6 dark:border-gray-700">
                    <x-ui.button type="submit" variant="accent">
                        Save settings
                    </x-ui.button>
                </div>
            </form>
        </div>
    @endfragment
@endsection
