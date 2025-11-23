@extends('emails.layouts.base')

@section('title', 'Welcome to Delight!')

@section('content')
<h2 class="greeting">Welcome to Delight, {{ $user->name }}!</h2>

<p class="message">
    We're thrilled to have you join our community. Delight is designed to help you build a consistent Bible reading habit that brings you joy.
</p>

<div class="button-container">
    <a href="{{ url('/dashboard') }}" class="button">Start Your Journey</a>
</div>

<p class="message" style="margin-bottom: 32px;">
    Here are a few ways to get the most out of your experience:
</p>

<!-- Log Reading Section -->
<div class="card">
    <a href="{{ url('/logs/create') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6.03v13m0-13c-2.819-.831-4.715-1.076-8.029-1.023A.99.99 0 0 0 3 6v11c0 .563.466 1.014 1.03 1.007 3.122-.043 5.018.212 7.97 1.023m0-13c2.819-.831 4.715-1.076 8.029-1.023A.99.99 0 0 1 21 6v11c0 .563-.466 1.014-1.03 1.007-3.122-.043-5.018.212-7.97 1.023" />
                </svg>
            </span>
            <span class="card-title" style="color: #2563eb;">Log Your Reading</span>
        </div>
        <p class="card-text">
            Just finished a chapter? Record your daily reading quickly and keep your streak alive.
        </p>
    </a>
</div>

<!-- Dashboard Section -->
<div class="card">
    <a href="{{ url('/dashboard') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 15v4m6-6v6m6-4v4m6-6v6M3 11l6-5 6 5 5.5-5.5" />
                </svg>
            </span>
            <span class="card-title" style="color: #2563eb;">Your Dashboard</span>
        </div>
        <p class="card-text">
            View your current streak, weekly journey progress, and access all your reading tools in one place.
        </p>
    </a>
</div>

<!-- History Logs Section -->
<div class="card">
    <a href="{{ url('/logs') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                </svg>
            </span>
            <span class="card-title" style="color: #2563eb;">History Logs</span>
        </div>
        <p class="card-text">
            Reflect on what you've read. View your complete reading history and revisit past passages in your Logs.
        </p>
    </a>
</div>

<!-- Feedback Section -->
<div class="card">
    <a href="{{ url('/feedback') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 9h5m3 0h2M7 12h2m3 0h5M5 5h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-6.616a1 1 0 0 0-.67.257l-2.88 2.592A.5.5 0 0 1 8 18.477V17a1 1 0 0 0-1-1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
                </svg>
            </span>
            <span class="card-title" style="color: #2563eb;">We Value Your Feedback</span>
        </div>
        <p class="card-text">
            We are constantly improving. If you have suggestions or run into any issues, please let us know via our Feedback form.
        </p>
    </a>
</div>

<p class="message" style="margin-top: 40px;">
    We're excited to see your reading journey unfold!
</p>

@endsection
