@extends('layouts.authenticated')

@section('page-title', 'Manage Announcements')

@section('content')
    <div class="max-w-5xl w-full mx-auto">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Announcements</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Manage your product updates and notifications.</p>
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
                                        class="px-3 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th scope="col"
                                        class="px-3 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Publish Date</th>
                                    <th scope="col"
                                        class="px-3 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($announcements as $announcement)
                                    @php
                                        $status = 'Draft';
                                        if ($announcement->starts_at && $announcement->starts_at->isFuture()) {
                                            $status = 'Scheduled';
                                        } elseif (
                                            $announcement->starts_at &&
                                            (!$announcement->ends_at || $announcement->ends_at->isFuture())
                                        ) {
                                            $status = 'Active';
                                        } elseif ($announcement->ends_at && $announcement->ends_at->isPast()) {
                                            $status = 'Expired';
                                        }
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td
                                            class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">
                                            {{ $announcement->title }}
                                            <div class="text-xs text-gray-500 dark:text-gray-500 font-normal">
                                                /{{ $announcement->slug }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <span
                                                class="inline-flex rounded-full px-2 text-xs font-medium leading-5 
                                                                            {{ $announcement->type === 'warning' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                                                            {{ $announcement->type === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                                                            {{ $announcement->type === 'info' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}">
                                                {{ ucfirst($announcement->type) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $announcement->starts_at ? $announcement->starts_at->format('M j, Y H:i') : 'Draft' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium 
                                                                            {{ $status === 'Active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                                                            {{ $status === 'Scheduled' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                                                            {{ $status === 'Expired' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}">
                                                {{ $status }}
                                            </span>
                                        </td>
                                        <td
                                            class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <a href="{{ route('announcements.show', $announcement->slug) }}"
                                                target="_blank"
                                                class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">View</a>
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
