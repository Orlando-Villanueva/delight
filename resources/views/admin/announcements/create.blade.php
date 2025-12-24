@extends('layouts.authenticated')

@section('page-title', 'New Announcement')

@section('content')
    <div class="px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="min-w-0 flex-1">
                <h2
                    class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:truncate sm:text-3xl sm:tracking-tight">
                    Create
                    Announcement</h2>
            </div>
            <div class="mt-4 flex md:ml-4 md:mt-0">
                <a href="{{ route('admin.announcements.index') }}"
                    class="inline-flex items-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    &larr; Back
                </a>
            </div>
        </div>

        <form action="{{ route('admin.announcements.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: Main Content -->
                <div class="lg:col-span-2 space-y-8">
                    <div
                        class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden p-6 sm:p-8">
                        <div class="space-y-6">
                            <div>
                                <label for="title"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Title</label>
                                <div class="mt-2">
                                    <input type="text" name="title" id="title"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="e.g. New Feature: Streak Protectors" required>
                                </div>
                            </div>

                            <div>
                                <label for="content"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Content
                                    (Markdown)</label>
                                <div class="mt-2">
                                    <textarea id="content" name="content" rows="15"
                                        class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 font-mono"
                                        placeholder="# Hello World..." required></textarea>
                                    <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">You can use
                                        standard Markdown to format your announcement.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sidebar -->
                <div class="space-y-6">
                    <!-- Publishing Card -->
                    <div
                        class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden p-6">
                        <h3 class="text-base font-semibold leading-7 text-gray-900 dark:text-white mb-4">Publishing</h3>

                        <div class="space-y-6">
                            <div>
                                <label for="type"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Type</label>
                                <div class="mt-2">
                                    <select id="type" name="type"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                        <option value="info">Info</option>
                                        <option value="warning">Warning</option>
                                        <option value="success">Success</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="starts_at"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Publish
                                    Date</label>
                                <div class="mt-2">
                                    <input type="datetime-local" name="starts_at" id="starts_at"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Leave blank to publish
                                        immediately.</p>
                                </div>
                            </div>

                            <div>
                                <label for="ends_at"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Valid
                                    Until</label>
                                <div class="mt-2">
                                    <input type="datetime-local" name="ends_at" id="ends_at"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Optional expiry date.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-700">
                            <button type="submit"
                                class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 transition-colors">
                                Save Announcement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection