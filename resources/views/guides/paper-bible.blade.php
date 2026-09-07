@extends('layouts.guide')

@php
    $title = 'How to keep track of Bible reading with a paper Bible';
    $description = 'Keep track of chapters and reading frequency alongside your paper Bible. Learn a simple method and see how Delight organizes your reading record.';
    $canonicalUrl = route('guides.paper-bible');
    $imageUrl = asset('images/guide-paper-bible-social.jpg');
    $heroUrl = asset('images/guide-paper-bible-hero.jpg');
    $imageAlt = 'Track your paper-Bible reading — an open Bible with a blue ribbon beside a reading journal in warm window light.';
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $title,
        'description' => $description,
        'url' => $canonicalUrl,
        'mainEntityOfPage' => $canonicalUrl,
        'image' => $heroUrl,
        'author' => ['@type' => 'Person', 'name' => 'Orlando Villanueva'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Delight'],
        'datePublished' => '2026-09-07',
    ];
@endphp

@section('title', $title.' - Delight')

@section('meta')
    <meta name="description" content="{{ $description }}">
    <meta name="author" content="Orlando Villanueva">
    <meta name="robots" content="{{ app()->environment('production') ? 'index, follow' : 'noindex, nofollow' }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="2026-09-07">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="Delight">
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:alt" content="{{ $imageAlt }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $imageUrl }}">
    <meta name="twitter:image:alt" content="{{ $imageAlt }}">
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('guide-category', 'PRACTICAL BIBLE READING')
@section('guide-byline', 'By Orlando Villanueva · Creator of Delight · 4 min read')

@section('guide-dates')
    Published <time datetime="2026-09-07">September 7, 2026</time>
    {{-- After a post-publication revision, add: · Updated <time datetime="YYYY-MM-DD">Month D, YYYY</time> and matching dateModified metadata. --}}
@endsection

@section('guide-hero')
    <img src="{{ $heroUrl }}" alt="Illustration of an open Bible with a blue ribbon bookmark beside a journal and pen in warm window light." width="1599" height="900" fetchpriority="high" class="h-auto w-full rounded-xl border border-gray-300 dark:border-gray-700">
@endsection

@section('guide-body')
    <p>You finish reading, put a bookmark in your Bible, and get on with your day. It helps you find your place tomorrow. Remembering which other chapters you have read, or how often you have been reading, takes a little more than a bookmark.</p>
    <p>A simple reading record can answer both questions without changing where you read. Start with the passage and the date. You can keep them on paper or use a tracker such as Delight to organize your reading history, chapter progress, and reading frequency together.</p>
    <h2>What do you want to remember?</h2>
    <p>Your current place, completed passages, and reading frequency are different things. Decide which you want your record to show:</p>
    <ul>
        <li><strong>Your next passage:</strong> where to pick up when you open your Bible.</li>
        <li><strong>Chapters already read:</strong> what you have covered, including across different books.</li>
        <li><strong>Reading frequency:</strong> which days you read and how often you are returning to Scripture.</li>
        <li><strong>Optional details:</strong> progress through a reading plan or a note you want to revisit.</li>
    </ul>
    <p>You do not need an elaborate journal to begin. Recording what you read and when you read it gives you something concrete to return to.</p>
    <h2>Keep a simple paper record</h2>
    <p>A bookmark is enough to hold your place. A reading plan with checkboxes can keep track of completed assignments, while a chapter checklist suits reading in your own order. Keep either with your Bible and mark passages as you finish them.</p>
    <p>If you also want to see reading frequency, write the actual reading date beside each entry. The date printed on a plan is the intended schedule; your own date records when you read. A dated notebook works too. For example, “September 1 — John 1” is enough to preserve both the passage and the day.</p>
    <p>As the record grows, you can review those dates and chapters yourself, or let a digital tracker organize them into a history and calendar.</p>
    <h2>Track your paper-Bible reading with Delight</h2>
    <p>Delight is a Bible reading tracker that keeps that record for you after you log your readings. You continue reading from your paper Bible, then enter the chapters you finished. A reading plan is optional.</p>
    <p>After creating an account, you can record John 1 like this:</p>
    <ol>
        <li>Open the reading form and choose <strong>Today</strong>.</li>
        <li>Choose <strong>John</strong> and enter <strong>1</strong> as the start chapter. If you read several chapters together, enter the last one as the end chapter.</li>
        <li>Add a note if there is something you want to remember, then save.</li>
    </ol>
    <p>If you read yesterday and forgot to record it, choose <strong>Yesterday</strong>. Delight tracks completed chapters rather than individual verses, so keep a bookmark for your place within a chapter and log it when you finish.</p>
    <figure class="not-prose my-8">
        <img src="{{ asset('images/screenshots/guide-reading-form.png') }}" alt="Delight reading form with Today selected, John chosen, start chapter 1, and an optional notes field." width="973" height="1698" loading="lazy" class="mx-auto h-auto w-full max-w-sm rounded-xl border border-gray-300 dark:border-gray-700">
        <figcaption class="mt-3 text-center text-sm leading-6 text-gray-600 dark:text-gray-400">Choose the book and chapters you finished, then add an optional note.</figcaption>
    </figure>
    <h2>See what you read and how often</h2>
    <p>Imagine your record contains John 1 on September 1, John 2–3 on September 3, and Psalm 23 on September 6. That is four chapters across three reading days. The dates are not consecutive, but they still show how often you read that week.</p>
    <p>In Delight, those entries contribute to several views:</p>
    <ul>
        <li><strong>Reading history</strong> keeps the passages you recorded together.</li>
        <li><strong>Book progress</strong> shows chapter coverage across the books you read.</li>
        <li><strong>Reading Calendar</strong> shows days with recorded readings and the month's “Days Read” count.</li>
        <li><strong>Your streak</strong> shows consecutive reading days.</li>
    </ul>
    <p>You record the reading once, and these views help you review it from different angles. You can see both your frequency and your current streak without counting dates yourself. The picture reflects what you log, so readings you leave unrecorded will not appear.</p>
    <figure class="not-prose my-8">
        <img src="{{ asset('images/screenshots/guide-reading-calendar.png') }}" alt="September 2026 Reading Calendar showing readings on September 1 through 6, with 6 days read and 6 chapters." width="938" height="1160" loading="lazy" class="mx-auto h-auto w-full max-w-sm rounded-xl border border-gray-300 dark:border-gray-700">
        <figcaption class="mt-3 text-center text-sm leading-6 text-gray-600 dark:text-gray-400">A Reading Calendar from the creator's account: six reading days and six chapters recorded in September.</figcaption>
    </figure>
    <h2>Use it alongside your Bible</h2>
    <p>Delight is currently free to use, with an account required to save your reading history. Open it in your web browser or install it on your phone's home screen as a progressive web app (PWA), where supported. It needs an internet connection to load readings and save new logs. A public native iPhone or Android app is not currently available.</p>
    <p>You can start with your next reading; you do not need to reconstruct your entire reading history. Keep the record useful to you. Chapters and reading days describe activity, not your understanding of Scripture or your spiritual maturity.</p>
    <p>Create a free Delight account, log your next reading, and begin seeing your chapter progress and reading frequency in one place.</p>
@endsection

@section('guide-cta')
    <a href="{{ auth()->check() ? route('logs.create') : route('register') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-blue-700 px-6 py-3 text-base font-semibold text-white hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600 dark:bg-blue-600 dark:hover:bg-blue-500">
        {{ auth()->check() ? 'Log a Reading' : 'Create Your Free Account' }}
    </a>
@endsection
