@extends('layouts.authenticated')

@php($isEditing = isset($announcement))

@section('page-title', $isEditing ? 'Edit Announcement Draft' : 'New Announcement')

@section('content')
    <div class="w-full flex-1 flex flex-col">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ $isEditing ? 'Edit Announcement Draft' : 'Create Announcement' }}
                </h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $isEditing ? 'Revise this draft before publication.' : 'Publish an update now or schedule it for later.' }}
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <a href="{{ route('admin.announcements.index') }}"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto transition-colors dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700">
                    &larr; Back
                </a>
            </div>
        </div>

        <form action="{{ $isEditing ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" method="POST" class="mt-4 flex-1 flex flex-col">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            @if ($errors->any())
                <div role="alert"
                    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-medium">The announcement could not be saved.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div
                class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden flex-1 flex flex-col">
                <div class="p-6 sm:p-8 grid grid-cols-1 xl:grid-cols-4 gap-8 flex-1">
                    <!-- Main Content (Left) -->
                    <div class="xl:col-span-3 space-y-6 flex flex-col min-h-0">
                        <!-- Title -->
                        <div>
                            <label for="title"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Title</label>
                            <input type="text" name="title" id="title"
                                value="{{ old('title', $announcement->title ?? '') }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                placeholder="e.g. New Feature: Streak Protectors" required>
                        </div>

                        <!-- Publication Slug -->
                        <div>
                            <label for="slug"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Publication Slug
                                <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
                            <input type="text" name="slug" id="slug"
                                value="{{ old('slug', $announcement->slug ?? (filled(old('title')) ? Str::slug((string) old('title')) : '')) }}"
                                aria-describedby="slug-help @error('slug') slug-error @enderror"
                                @if($errors->has('slug')) aria-invalid="true" @endif
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                placeholder="new-feature-streak-protectors">
                            <p id="slug-help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to
                                generate it from the title.</p>
                            @error('slug')
                                <p id="slug-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Hero Image -->
                        <div>
                            <label for="hero_image_path"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Hero Image Path</label>
                            <input type="text" name="hero_image_path" id="hero_image_path"
                                value="{{ old('hero_image_path', $announcement->hero_image_path ?? '') }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                placeholder="images/feature-update-hero.png" required>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Place article hero images in
                                public/images and reference them from here.</p>
                        </div>

                        <!-- Social Image -->
                        <div>
                            <label for="social_image_path"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Social Image Path</label>
                            <input type="text" name="social_image_path" id="social_image_path"
                                value="{{ old('social_image_path', $announcement->social_image_path ?? '') }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                placeholder="images/feature-update-social.jpg">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional 1200x630 image for link
                                previews. Leave blank to use the hero image.</p>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 flex flex-col min-h-0" x-data="{ mode: 'write' }">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <label for="content"
                                    class="block text-sm font-medium text-gray-900 dark:text-white">Content
                                    (Markdown)</label>
                                <div class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-1 text-xs font-medium text-gray-600 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                    <button type="button"
                                        class="rounded-md px-3 py-1.5 transition-colors"
                                        :class="mode === 'write' ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'"
                                        :aria-pressed="mode === 'write'"
                                        @click="mode = 'write'">
                                        Write
                                    </button>
                                    <button type="button"
                                        class="rounded-md px-3 py-1.5 transition-colors"
                                        :class="mode === 'preview' ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'"
                                        :aria-pressed="mode === 'preview'"
                                        @click="mode = 'preview'"
                                        hx-post="{{ route('admin.announcements.preview-markdown') }}"
                                        hx-target="#announcement-preview"
                                        hx-swap="innerHTML"
                                        hx-include="closest form"
                                        hx-params="not _method">
                                        Preview
                                    </button>
                                </div>
                            </div>

                            <div class="relative mt-3 min-h-96 flex-1 xl:min-h-0">
                                <textarea id="content" name="content" rows="20"
                                    class="absolute inset-0 block h-full w-full p-4 text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 font-mono resize-none"
                                    placeholder="# Hello World..." required
                                    x-show="mode === 'write'" x-cloak>{{ old('content', $announcement->content ?? '') }}</textarea>

                                <div id="announcement-preview"
                                    class="absolute inset-0 h-full w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-4 text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    x-show="mode === 'preview'" x-cloak>
                                    @fragment('announcement-preview')
                                        @if(!($previewIsEmpty ?? true))
                                            <div class="prose prose-blue prose-lg max-w-none dark:prose-invert">
                                                {!! $previewHtml !!}
                                            </div>
                                        @else
                                            <div class="text-sm text-gray-500 dark:text-gray-300">
                                                Nothing to preview yet. Add some Markdown to see the formatted output.
                                            </div>
                                        @endif
                                    @endfragment
                                </div>
                            </div>

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Supports standard Markdown formatting.
                            </p>
                        </div>
                    </div>

                    <!-- Sidebar (Right) -->
                    <div class="xl:border-l xl:border-gray-100 xl:dark:border-gray-700 xl:pl-8 xl:flex xl:flex-col">
                        <div class="space-y-6">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Publishing</h3>

                            <!-- Publish Date -->
                            <div>
                                <label for="starts_at"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Publish
                                    Date</label>
                                <input type="datetime-local" name="starts_at" id="starts_at"
                                    value="{{ old('starts_at', isset($announcement) ? $announcement->starts_at?->format('Y-m-d\TH:i') : '') }}"
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
                                    value="{{ old('ends_at', isset($announcement) ? $announcement->ends_at?->format('Y-m-d\TH:i') : '') }}"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional expiry date.</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="pt-6 mt-6 border-t border-gray-100 dark:border-gray-700 xl:mt-auto">
                            @if ($isEditing)
                                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
                                    Saving keeps this announcement as a private draft. It will not authorize or send email.
                                </div>
                            @else
                                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
                                    Publishing authorizes an email to every eligible user. A future publish date delays both
                                    the public announcement and its email delivery until that time.
                                </div>
                            @endif
                            <button type="submit"
                                class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 transition-colors">
                                {{ $isEditing ? 'Save draft changes' : 'Publish or schedule announcement' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
