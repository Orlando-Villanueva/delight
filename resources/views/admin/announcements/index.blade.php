@extends('layouts.authenticated')

@section('page-title', 'Manage Announcements')

@section('content')
    <div class="max-w-5xl w-full mx-auto">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Announcements</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Manage your product updates and notifications.</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Transport submission does not confirm inbox delivery.</p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <a href="{{ route('admin.announcements.create') }}"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto transition-colors dark:hover:bg-blue-500">
                    New Announcement
                </a>
            </div>
        </div>

        <div class="mt-4 flex flex-col">
            <div class="overflow-x-auto rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="inline-block min-w-full align-middle">
                    @if (session('success'))
                        <div
                            class="px-6 py-4 bg-green-50 dark:bg-green-900/30 border-b border-green-200 dark:border-green-800 text-green-700 dark:text-green-400">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="overflow-hidden bg-white dark:bg-gray-800">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col"
                                        class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider sm:pl-6">
                                        Title
                                    </th>
                                    <th scope="col"
                                        class="hidden md:table-cell px-3 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Publish Date</th>
                                    <th scope="col"
                                        class="px-3 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col"
                                        class="px-3 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Email delivery
                                    </th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="announcement-table-body"
                                class="divide-y divide-gray-200 dark:divide-gray-700"
                                @if ($hasActiveEmailBroadcasts)
                                    hx-get="{{ request()->fullUrl() }}"
                                    hx-trigger="every 15s"
                                    hx-select="#announcement-table-body"
                                    hx-swap="outerHTML"
                                @endif>
                                @foreach ($announcements as $announcement)
                                    @php
                                        $status = 'Draft';
                                        if (!$announcement->is_draft && $announcement->starts_at && $announcement->starts_at->isFuture()) {
                                            $status = 'Scheduled';
                                        } elseif (!$announcement->is_draft &&
                                            $announcement->starts_at &&
                                            (!$announcement->ends_at || $announcement->ends_at->isFuture())
                                        ) {
                                            $status = 'Active';
                                        } elseif (!$announcement->is_draft && $announcement->ends_at && $announcement->ends_at->isPast()) {
                                            $status = 'Expired';
                                        }
                                    @endphp
                                    @php
                                        $delivery = $deliverySummaries[$announcement->id];
                                        $emailStatusClasses = match ($delivery['status']) {
                                            'Needs attention' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                            'Uncertain', 'Audience not finalized', 'Awaiting completion' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                            'Scheduled', 'Pending recipients' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            'Processing completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td
                                            class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">
                                            {{ $announcement->title }}
                                            <div class="text-xs text-gray-500 dark:text-gray-500 font-normal">
                                                /{{ $announcement->slug }}</div>
                                        </td>
                                        <td
                                            class="hidden md:table-cell whitespace-nowrap px-3 py-4 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $announcement->is_draft ? 'Draft' : $announcement->starts_at?->format('M j, Y H:i') }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                                            {{ $status === 'Active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                                                            {{ $status === 'Scheduled' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                                                            {{ in_array($status, ['Draft', 'Expired'], true) ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}">
                                                {{ $status }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300" aria-live="polite">
                                            <div class="flex min-w-44 flex-col items-start gap-2">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $emailStatusClasses }}">
                                                    {{ $delivery['status'] }}
                                                </span>

                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $delivery['authorized'] ? 'Email authorized' : 'Email not authorized' }}.
                                                </p>

                                                @if ($delivery['audience_finalized'] || $delivery['total'] > 0 || $delivery['completed'])
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $delivery['handled'] }} of {{ $delivery['total'] }} handled
                                                        @if ($delivery['submitted'] > 0)
                                                            &middot; {{ $delivery['submitted'] }} transport-submitted
                                                        @endif
                                                        @if ($delivery['pending'] > 0)
                                                            &middot; {{ $delivery['pending'] }} pending
                                                        @endif
                                                        @if ($delivery['skipped'] > 0)
                                                            &middot; {{ $delivery['skipped'] }} skipped
                                                        @endif
                                                        @if ($delivery['failed'] > 0)
                                                            &middot; {{ $delivery['failed'] }} failed
                                                        @endif
                                                        @if ($delivery['uncertain'] > 0)
                                                            &middot; {{ $delivery['uncertain'] }} uncertain
                                                        @endif
                                                    </p>

                                                    @if ($announcement->email_broadcast_completed_at)
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            @if ($announcement->emailBroadcastDurationForHumans())
                                                                Completed in {{ $announcement->emailBroadcastDurationForHumans() }}
                                                            @else
                                                                Processing completed
                                                            @endif
                                                            &middot; {{ $announcement->email_broadcast_completed_at->diffForHumans() }}
                                                        </p>
                                                    @elseif ($delivery['audience_finalized'])
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            Started {{ $announcement->email_audience_finalized_at->diffForHumans() }}
                                                            @if ($announcement->latestEmailDelivery)
                                                                &middot; Last activity {{ $announcement->latestEmailDelivery->updated_at->diffForHumans() }}
                                                            @endif
                                                        </p>
                                                    @endif
                                                @endif

                                                @if ($announcement->latestFailedEmailDelivery)
                                                    <p class="max-w-xs whitespace-normal text-xs text-red-700 dark:text-red-300">
                                                        {{ $announcement->latestFailedEmailDelivery->failure_reason }}
                                                        <span class="block text-red-600/80 dark:text-red-300/80">
                                                            {{ $announcement->latestFailedEmailDelivery->failed_at?->format('M j, Y H:i') }}
                                                        </span>
                                                    </p>
                                                @endif

                                                @if ($delivery['failed'] > 0)
                                                    <form method="POST" action="{{ route('admin.announcements.email-deliveries.retry', $announcement) }}">
                                                        @csrf
                                                        <button type="submit"
                                                            class="inline-flex items-center rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-700 shadow-sm transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-red-800 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-900/20 dark:focus:ring-offset-gray-800">
                                                            Retry {{ $delivery['failed'] }} failed
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                        <td
                                            class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            @if ($announcement->isPublished())
                                                <a href="{{ route('announcements.show', $announcement->slug) }}"
                                                    target="_blank"
                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">View</a>
                                            @else
                                                <div class="flex items-center justify-end gap-3">
                                                    @if ($announcement->is_draft)
                                                        <a href="{{ route('admin.announcements.edit', $announcement) }}"
                                                            class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Edit</a>
                                                    @endif
                                                    <a href="{{ route('admin.announcements.preview', $announcement->slug) }}"
                                                        target="_blank"
                                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Preview</a>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                {{ $announcements->links() }}
            </div>
        </div>
    </div>
@endsection
