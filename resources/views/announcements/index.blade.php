@extends('layouts.reader')

@section('title', 'Product Updates - Delight')
@section('meta')
    <meta name="description" content="Latest news, updates, and feature releases from Delight.">
@endsection

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-16">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">Product Updates</h1>
            <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">
                News, features, and improvements from the Delight team.
            </p>
        </div>

        <div class="space-y-16">
            @forelse($announcements as $announcement)
                <article class="flex flex-col items-start justify-between group">
                    <div class="flex items-center gap-x-4 text-xs">
                        <time datetime="{{ $announcement->starts_at->toIso8601String() }}"
                            class="text-gray-500 dark:text-gray-400">
                            {{ $announcement->starts_at->format('M j, Y') }}
                        </time>
                        @if ($announcement->type !== 'info')
                            <span
                                class="relative z-10 rounded-full px-3 py-1.5 font-medium
                                                        {{ $announcement->type === 'success' ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                                        {{ $announcement->type === 'warning' ? 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                                    ">
                                {{ ucfirst($announcement->type) }}
                            </span>
                        @endif
                    </div>

                    <div class="relative mt-3">
                        <h3
                            class="text-xl font-semibold leading-6 text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            <a href="{{ route('announcements.show', $announcement->slug) }}">
                                <span class="absolute inset-0"></span>
                                {{ $announcement->title }}
                            </a>
                        </h3>
                        <p class="mt-3 line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                            {{ Str::limit(strip_tags(Str::markdown($announcement->content)), 200) }}
                        </p>
                    </div>
                </article>
            @empty
                <div class="text-center py-12">
                    <p class="text-gray-500 dark:text-gray-400">No updates yet.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-12">
            {{ $announcements->links() }}
        </div>
    </div>
@endsection
