@extends('layouts.authenticated')

@section('page-title', 'Settings')
@section('page-subtitle', 'Manage your reading preferences.')

@section('content')
    @fragment('page-content')
        <x-ui.page-shell width="medium">
            <x-ui.page-header
                title="Settings"
                subtitle="Manage your reading preferences."
            />

            <form method="POST" action="{{ route('settings.update') }}"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                @csrf
                @method('PATCH')

                <div data-deuterocanonical-setting data-settings-url="{{ route('settings.update') }}"
                    class="space-y-2">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Deuterocanonical books</h2>

                        <div class="flex flex-col items-end gap-2">
                            <label class="inline-flex cursor-pointer items-center">
                                <input type="hidden" name="include_deuterocanonical" value="0">
                                <input type="checkbox" name="include_deuterocanonical" value="1"
                                    aria-label="Include deuterocanonical books"
                                    @checked(auth()->user()?->includesDeuterocanonicalBooks())
                                    data-deuterocanonical-toggle
                                    class="peer sr-only">
                                <span
                                    class="relative h-6 w-11 rounded-full bg-gray-200 after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-disabled:cursor-not-allowed peer-disabled:opacity-60 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:bg-gray-700 dark:peer-focus:ring-primary-800 rtl:peer-checked:after:-translate-x-full"></span>
                                <span data-deuterocanonical-toggle-label class="sr-only">
                                    {{ auth()->user()?->includesDeuterocanonicalBooks() ? 'Enabled' : 'Disabled' }}
                                </span>
                            </label>

                            <p data-deuterocanonical-status hidden
                                class="text-xs font-medium text-gray-500 dark:text-gray-400" role="status" aria-live="polite"></p>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm leading-6 text-gray-600 dark:text-gray-400">
                            Include Tobit, Judith, Wisdom, Sirach, Baruch, 1-2 Maccabees, and the Catholic-integrated
                            additions to Esther and Daniel when logging readings.
                        </p>
                    </div>
                </div>

                @php
                    $accountHasReminderDevices = auth()->user()?->pushSubscriptions()->exists() ?? false;
                @endphp

                <div id="reading-reminders" data-reading-reminders-settings
                    data-device-enabled="false"
                    data-account-has-devices="{{ $accountHasReminderDevices ? 'true' : 'false' }}"
                    data-push-public-key="{{ config('webpush.vapid.public_key') }}"
                    data-subscription-url="{{ route('push.subscriptions.store') }}"
                    data-status-url="{{ route('push.subscriptions.status') }}"
                    data-unsubscribe-url="{{ route('push.subscriptions.destroy') }}"
                    data-disconnect-all-url="{{ route('push.subscriptions.destroy-all') }}"
                    data-preferences-url="{{ route('push.preferences.update') }}"
                    class="mt-6 flex flex-col gap-4 border-t border-gray-200 pt-6 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Reading reminders</h2>
                            <p data-reading-reminders-status hidden class="text-sm leading-6 text-gray-600 dark:text-gray-400"></p>
                        </div>

                        <button type="button" role="switch" aria-label="Reading reminders for this browser" data-reading-reminders-toggle
                            aria-checked="false"
                            class="group mt-0.5 inline-flex cursor-pointer items-center">
                            <span
                                class="relative h-6 w-11 rounded-full bg-gray-200 transition group-aria-checked:bg-primary-600 group-disabled:cursor-not-allowed group-disabled:opacity-60 group-focus:outline-none group-focus:ring-4 group-focus:ring-primary-300 dark:bg-gray-700 dark:group-focus:ring-primary-800 after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all group-aria-checked:after:translate-x-full rtl:group-aria-checked:after:-translate-x-full"></span>
                            <span data-reading-reminders-toggle-label class="sr-only">
                                Disabled
                            </span>
                        </button>
                    </div>

                    <div data-reading-reminders-progress hidden
                        class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-900 dark:border-blue-900/60 dark:bg-blue-900/20 dark:text-blue-100">
                        Choose Allow in the browser permission prompt to finish setup.
                    </div>

                    <div data-reading-reminders-ios-guidance hidden
                        class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm leading-6 text-yellow-900 dark:border-yellow-900/60 dark:bg-yellow-900/20 dark:text-yellow-100">
                        iPhone and iPad support requires Safari -> Add to Home Screen -> open Delight from the Home Screen icon -> enable notifications.
                    </div>

                    <div data-reading-reminders-unsupported hidden
                        class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-6 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        This browser does not appear to support web push reminders. Try an installed Delight PWA or a current desktop browser with notifications enabled.
                    </div>

                    <div data-reading-reminders-blocked hidden
                        class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm leading-6 text-yellow-900 dark:border-yellow-900/60 dark:bg-yellow-900/20 dark:text-yellow-100">
                        Notifications are blocked for this browser. Allow notifications in your browser site settings, then return here to enable reminders.
                    </div>

                    <div data-reading-reminders-error hidden
                        class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-800 dark:border-red-900/60 dark:bg-red-900/20 dark:text-red-100">
                        Reminder setup could not finish. Refresh the page and try again.
                    </div>

                    <div>
                        <p data-reading-reminders-preferences-status hidden
                            class="text-xs font-medium text-gray-500 dark:text-gray-400" role="status" aria-live="polite"></p>

                        <div
                            class="divide-y divide-gray-200 border-t border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <label class="flex items-start gap-3 py-3">
                                @if ($accountHasReminderDevices)
                                    <input type="hidden" name="daily_reading_reminder_enabled" value="0">
                                @endif
                                <input type="checkbox" name="daily_reading_reminder_enabled" value="1"
                                    @checked(auth()->user()?->hasDailyReadingReminderEnabled())
                                    @disabled(! $accountHasReminderDevices)
                                    data-reading-reminders-preference="daily_reading_reminder_enabled"
                                    class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="space-y-1">
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">09:00 daily reminder</span>
                                    <span class="block text-sm leading-6 text-gray-600 dark:text-gray-400">Sends only when today has no reading log.</span>
                                </span>
                            </label>

                            <label class="flex items-start gap-3 py-3">
                                @if ($accountHasReminderDevices)
                                    <input type="hidden" name="streak_warning_enabled" value="0">
                                @endif
                                <input type="checkbox" name="streak_warning_enabled" value="1"
                                    @checked(auth()->user()?->hasStreakWarningEnabled())
                                    @disabled(! $accountHasReminderDevices)
                                    data-reading-reminders-preference="streak_warning_enabled"
                                    class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="space-y-1">
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">18:00 streak warning</span>
                                    <span class="block text-sm leading-6 text-gray-600 dark:text-gray-400">Sends only when yesterday was read and today is still unread.</span>
                                </span>
                            </label>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button type="button" data-reading-reminders-disconnect-all
                                @if (! $accountHasReminderDevices) hidden @endif
                                class="text-sm font-medium text-gray-500 underline-offset-4 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200">
                                Turn off reminders everywhere
                            </button>
                        </div>
                    </div>

                    <input type="hidden" name="push_notification_timezone" value="{{ auth()->user()?->pushNotificationTimezone() }}" data-push-timezone>
                </div>

                <noscript>
                    <div class="grid gap-4 border-t border-gray-200 pt-6 dark:border-gray-700 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <div class="min-h-5">
                            @if (session('status'))
                                <p class="text-sm font-medium text-success-600 dark:text-success-400" role="status" aria-live="polite">
                                    {{ session('status') }}
                                </p>
                            @endif
                        </div>

                        <x-ui.button type="submit" variant="accent" class="justify-self-end">
                            Save settings
                        </x-ui.button>
                    </div>
                </noscript>
            </form>
        </x-ui.page-shell>
    @endfragment
@endsection
