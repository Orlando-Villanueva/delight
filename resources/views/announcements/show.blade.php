@extends('layouts.reader')

@php($isPreview = $isPreview ?? false)

@section('title', ($isPreview ? 'Preview: ' : '') . $announcement->title . ' - Delight Updates')

@section('meta')
    @php($heroImageUrl = $announcement->heroImageUrl())
    @php($socialImageUrl = $announcement->socialImageUrl())
    @php($seoDescription = $announcement->seoDescription(150))
    <meta name="description" content="{{ $seoDescription }}">
    @if ($isPreview)
        <meta name="robots" content="noindex, nofollow">
    @else
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('announcements.show', $announcement->slug) }}">
        <meta property="og:title" content="{{ $announcement->title }}">
        <meta property="og:description" content="{{ $announcement->seoDescription(200) }}">
        <meta property="og:type" content="article">
        <meta property="og:url" content="{{ route('announcements.show', $announcement->slug) }}">
        <meta property="article:published_time" content="{{ $announcement->starts_at->toIso8601String() }}">

        <!-- Social -->
        <meta property="og:image" content="{{ $socialImageUrl ?? asset('images/social-article.png') }}">
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:image" content="{{ $socialImageUrl ?? asset('images/social-article.png') }}">

        <!-- JSON-LD Schema -->
        <script type="application/ld+json">
                    {
                        "@@context": "https://schema.org",
                        "@@type": "BlogPosting",
                        "headline": "{{ $announcement->title }}",
                        "datePublished": "{{ $announcement->starts_at->toIso8601String() }}",
                        "dateModified": "{{ $announcement->updated_at->toIso8601String() }}",
                        @if ($socialImageUrl)
                        "image": "{{ $socialImageUrl }}",
                        @endif
                        "author": {
                            "@@type": "Organization",
                            "name": "Delight"
                        },
                        "publisher": {
                            "@@type": "Organization",
                            "name": "Delight",
                            "logo": {
                                 "@@type": "ImageObject",
                                 "url": "{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }}"
                            }
                        },
                        "description": {!! json_encode($seoDescription, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
                    }
        </script>
    @endif
@endsection

@section('content')
    @if ($isPreview)
        <aside
            class="mx-auto mb-8 flex max-w-4xl flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 shadow-sm dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-semibold">{{ $announcement->is_draft ? 'Draft preview' : 'Scheduled preview' }}</p>
                <p class="mt-1 text-sm">This announcement is not publicly visible yet.</p>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                @if ($announcement->is_draft)
                    <a href="{{ route('admin.announcements.edit', $announcement) }}"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-600 dark:hover:bg-blue-500 dark:focus:ring-offset-amber-950">
                        Edit draft
                    </a>
                @endif
                <a href="{{ route('admin.announcements.index') }}"
                    class="text-sm font-semibold text-amber-900 hover:text-amber-700 dark:text-amber-100 dark:hover:text-amber-300">
                    Back to announcements
                </a>
            </div>
        </aside>
    @endif

    <article class="mx-auto max-w-4xl">
        <header class="mb-10 text-center not-prose">
            <h1 class="mt-4 text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                {{ $announcement->title }}
            </h1>

            <div class="mt-4 flex items-center justify-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                <time datetime="{{ $announcement->starts_at->toIso8601String() }}">
                    {{ $announcement->starts_at->format('F j, Y') }}
                </time>
                &bull;
                <span>Delight Team</span>
            </div>
        </header>

        @if ($heroImageUrl = $announcement->heroImageUrl())
            <figure
                class="mb-12 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <img src="{{ $heroImageUrl }}" alt="{{ $announcement->title }}"
                    class="aspect-[2/1] w-full object-cover">
            </figure>
        @endif

        <div
            class="prose prose-blue prose-lg mx-auto dark:prose-invert
                prose-img:mx-auto prose-img:rounded-xl prose-img:border prose-img:border-gray-200 prose-img:shadow-sm dark:prose-img:border-gray-700">
            {!! Str::markdown($announcement->content) !!}
        </div>

        <div class="mx-auto mt-16 flex max-w-prose flex-wrap items-center gap-4 border-t border-gray-100 pt-10 dark:border-gray-800">
            @if ($isPreview && $announcement->is_draft)
                <a href="{{ route('admin.announcements.edit', $announcement) }}"
                    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-600 dark:hover:bg-blue-500 dark:focus:ring-offset-gray-900">
                    Edit draft
                </a>
            @endif
            <a href="{{ $isPreview ? route('admin.announcements.index') : route('announcements.index') }}"
                class="flex items-center gap-2 font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                &larr; {{ $isPreview ? 'Back to announcements' : 'Back to all updates' }}
            </a>
        </div>
    </article>
@endsection
