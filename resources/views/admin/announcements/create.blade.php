@extends('layouts.authenticated')

@section('page-title', 'New Announcement')

@section('content')
    <div class="w-full flex-1 flex flex-col">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Create Announcement</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Draft a new update for your users.</p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <a href="{{ route('admin.announcements.index') }}"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto transition-colors dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700">
                    &larr; Back
                </a>
            </div>
        </div>

        <form action="{{ route('admin.announcements.store') }}" method="POST" class="mt-4 flex-1 flex flex-col">
            @csrf

            <div
                class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden flex-1 flex flex-col">
                <div class="p-6 sm:p-8 grid grid-cols-1 xl:grid-cols-4 gap-8 flex-1">
                    <!-- Main Content (Left) -->
                    <div class="xl:col-span-3 space-y-6 flex flex-col">
                        <!-- Title -->
                        <div>
                            <label for="title"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Title</label>
                            <input type="text" name="title" id="title"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                placeholder="e.g. New Feature: Streak Protectors" required>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 flex flex-col">
                            <label for="content"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Content
                                (Markdown)</label>
                            <textarea id="content" name="content" rows="20"
                                class="block p-4 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 font-mono flex-1"
                                placeholder="# Hello World..." required></textarea>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Supports standard Markdown formatting.
                            </p>
                        </div>
                    </div>

                    <!-- Sidebar (Right) -->
                    <div class="xl:border-l xl:border-gray-100 xl:dark:border-gray-700 xl:pl-8 xl:flex xl:flex-col">
                        <div class="space-y-6">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Publishing</h3>

                            <!-- Type -->
                            <div>
                                <label for="type"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Type</label>
                                <select id="type" name="type"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                    <option value="info">Info</option>
                                    <option value="warning">Warning</option>
                                    <option value="success">Success</option>
                                </select>
                            </div>

                            <!-- Publish Date -->
                            <div>
                                <label for="starts_at"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Publish
                                    Date</label>
                                <input type="datetime-local" name="starts_at" id="starts_at"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to publish immediately.
                                </p>
                            </div>

                            <!-- Valid Until -->
                            <div>
                                <label for="ends_at"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Valid
                                    Until</label>
                                <input type="datetime-local" name="ends_at" id="ends_at"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional expiry date.</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="pt-6 mt-6 border-t border-gray-100 dark:border-gray-700 xl:mt-auto">
                            <button type="submit"
                                class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 transition-colors">
                                Save Announcement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
