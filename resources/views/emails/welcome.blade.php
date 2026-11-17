@extends('emails.layouts.base')

@section('title', __('Welcome to Delight!'))

@section('content')
<h2 class="greeting">{{ __('Welcome to Delight,') }} {{ $user->name }}!</h2>

<p class="message">
    {{ __("We're thrilled to have you join our community. Delight is designed to help you build a consistent Bible reading habit that brings you joy.") }}
</p>

<div class="button-container">
    <a href="{{ url('/dashboard') }}" class="button">{{ __("Start Your Journey") }}</a>
</div>

<p class="message" style="margin-bottom: 32px;">
    {{ __("Here are a few ways to get the most out of your experience:") }}
</p>

<!-- Log Reading Section -->
<div class="card">
    <a href="{{ url('/logs/create') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <span aria-hidden="true" style="font-size:16px; line-height:1;">📘</span>
            </span>
            <span class="card-title" style="color: #2563eb;">{{ __("Log Your Reading") }}</span>
        </div>
        <p class="card-text">
            {{ __("Just finished a chapter? Record your daily reading quickly and keep your streak alive.") }}
        </p>
    </a>
</div>

<!-- Dashboard Section -->
<div class="card">
    <a href="{{ url('/dashboard') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <span aria-hidden="true" style="font-size:16px; line-height:1;">📊</span>
            </span>
            <span class="card-title" style="color: #2563eb;">{{ __("Your Dashboard") }}</span>
        </div>
        <p class="card-text">
            {{ __("View your current streak, next milestone, achievements, and all your reading tools in one place.") }}
        </p>
    </a>
</div>

<!-- History Logs Section -->
<div class="card">
    <a href="{{ url('/logs') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <span aria-hidden="true" style="font-size:16px; line-height:1;">⏱️</span>
            </span>
            <span class="card-title" style="color: #2563eb;">{{ __("History Logs") }}</span>
        </div>
        <p class="card-text">
            {{ __("Reflect on what you've read. View your complete reading history and revisit past passages in your Logs.") }}
        </p>
    </a>
</div>

<!-- Feedback Section -->
<div class="card">
    <a href="{{ url('/feedback') }}" style="text-decoration: none; display: block;">
        <div class="card-heading" style="gap: 12px;">
            <span class="card-icon">
                <span aria-hidden="true" style="font-size:16px; line-height:1;">💬</span>
            </span>
            <span class="card-title" style="color: #2563eb;">{{ __("We Value Your Feedback") }}</span>
        </div>
        <p class="card-text">
            {{ __("We are constantly improving. If you have suggestions or run into any issues, please let us know via our Feedback form.") }}
        </p>
    </a>
</div>

<p class="message" style="margin-top: 40px;">
    {{ __("We're excited to see your reading journey unfold!") }}
</p>

@endsection
