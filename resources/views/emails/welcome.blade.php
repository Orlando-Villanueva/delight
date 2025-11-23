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

<!-- Weekly Journey Section -->
<div class="card">
    <a href="{{ url('/dashboard') }}" style="text-decoration: none; display: block;">
        <span class="card-title" style="color: #2563eb;">Weekly Journey</span>
        <p class="card-text">
            Track your progress through the Bible week by week. See your upcoming readings and stay on track with your goals on your Dashboard.
        </p>
    </a>
</div>

<!-- History Logs Section -->
<div class="card">
    <a href="{{ url('/logs') }}" style="text-decoration: none; display: block;">
        <span class="card-title" style="color: #2563eb;">History Logs</span>
        <p class="card-text">
            Reflect on what you've read. View your complete reading history and revisit past passages in your Logs.
        </p>
    </a>
</div>

<!-- Feedback Section -->
<div class="card">
    <a href="{{ url('/feedback') }}" style="text-decoration: none; display: block;">
        <span class="card-title" style="color: #2563eb;">We Value Your Feedback</span>
        <p class="card-text">
            We are constantly improving. If you have suggestions or run into any issues, please let us know via our Feedback form.
        </p>
    </a>
</div>

<p class="message" style="margin-top: 40px;">
    We're excited to see your reading journey unfold!
</p>

@endsection
