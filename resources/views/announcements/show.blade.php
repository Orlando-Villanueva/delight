@extends('layouts.reader')

@section('title', $announcement->title . ' - Delight Updates')

@section('meta')
    <meta name="description" content="{{ Str::limit(strip_tags(Str::markdown($announcement->content)), 150) }}">
    <meta property="og:title" content="{{ $announcement->title }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags(Str::markdown($announcement->content)), 200) }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('announcements.show', $announcement->slug) }}">
    <meta property="article:published_time" content="{{ $announcement->starts_at->toIso8601String() }}">

    <!-- JSON-LD Schema -->
    <script type="application/ld+json">
                    {
                        "@context": "https://schema.org",
                        "@type": "BlogPosting",
                        "headline": "{{ $announcement->title }}",
                        "datePublished": "{{ $announcement->starts_at->toIso8601String() }}",
                        "dateModified": "{{ $announcement->updated_at->toIso8601String() }}",
                        "author": {
                            "@type": "Organization",
                            "name": "Delight"
                        },
                        "publisher": {
                            "@type": "Organization",
                            "name": "Delight",
                            "logo": {
                                 "@type": "ImageObject",
                                 "url": "{{ asset('images/logo-64.png') }}"
                            }
                        },
                        "description": "{{ Str::limit(strip_tags(Str::markdown($announcement->content)), 150) }}"
                    }
                    </script>
@endsection

@section('content')
    <article class="prose prose-blue prose-lg mx-auto dark:prose-invert">
        <header class="mb-10 text-center not-prose">
            @if ($announcement->type !== 'info')
                <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium
                                                    {{ $announcement->type === 'success' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : '' }}
                                                    {{ $announcement->type === 'warning' ? 'bg-yellow-50 text-yellow-800 ring-1 ring-inset ring-yellow-600/20' : '' }}
                                                ">
                    {{ ucfirst($announcement->type) }}
                </span>
            @endif

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

        <div class="mt-10">
            {!! Str::markdown($announcement->content) !!}
        </div>

        <div class="mt-16 border-t border-gray-100 dark:border-gray-800 pt-10">
            <a href="{{ route('announcements.index') }}"
                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium flex items-center gap-2">
                &larr; Back to all updates
            </a>
        </div>
    </article>
@endsection
