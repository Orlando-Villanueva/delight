@extends('layouts.reader')

@section('content')
    <article>
        <header class="mb-8 sm:mb-10">
            <p class="mb-4 text-sm font-semibold tracking-wide text-blue-700 dark:text-blue-400">@yield('guide-category')</p>
            <h1 class="text-4xl font-bold leading-tight tracking-tight text-gray-900 dark:text-white sm:text-5xl">{{ $title }}</h1>
            <p class="mt-5 text-sm leading-6 text-gray-600 dark:text-gray-400">@yield('guide-byline')</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">@yield('guide-dates')</p>
        </header>

        <div class="mb-8 sm:mb-10">
            @yield('guide-hero')
        </div>

        <div class="prose prose-blue prose-lg max-w-none dark:prose-invert prose-headings:tracking-tight prose-p:my-5 prose-h2:mt-10 sm:prose-p:my-6 sm:prose-h2:mt-12 prose-a:underline">
            @yield('guide-body')
        </div>

        @hasSection('guide-cta')
            <div class="mt-8">
                @yield('guide-cta')
            </div>
        @endif
    </article>
@endsection
