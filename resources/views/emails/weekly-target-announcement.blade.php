@extends('emails.layouts.base')

@section('title', 'New Weekly Target Feature - Delight')

@section('content')
<h2 class="greeting">📊 Introducing Weekly Targets</h2>

<p class="message">
    We're excited to announce a powerful new addition to your Delight dashboard: <strong>Weekly Targets</strong>. This feature helps you build a sustainable Bible reading habit with a research-backed goal of reading 4 days per week.
</p>

<div class="alert alert-success">
    <span class="alert-title">Your New Weekly Progress Tracker</span>
    <p class="alert-text">
        See your current week's reading progress displayed as "3/4 days this week" with a visual progress bar that fills as you read throughout the week.
    </p>
</div>

<div class="card">
    <span class="card-title">What's New:</span>
    
    <div style="margin-bottom: 16px;">
        <strong style="color: #059669;">✅ Weekly Progress Tracking</strong><br>
        <span class="text-sm text-muted">See your current week's reading progress at a glance with "X/4 days this week" display</span>
    </div>

    <div style="margin-bottom: 16px;">
        <strong style="color: #059669;">📈 Visual Progress Bar</strong><br>
        <span class="text-sm text-muted">Watch your progress fill up throughout the week with an encouraging progress indicator</span>
    </div>

    <div style="margin-bottom: 16px;">
        <strong style="color: #059669;">🎯 Research-Backed Target</strong><br>
        <span class="text-sm text-muted">The 4-days-per-week goal is based on habit formation research for sustainable growth</span>
    </div>

    <div style="margin-bottom: 0;">
        <strong style="color: #059669;">🔄 Automatic Weekly Reset</strong><br>
        <span class="text-sm text-muted">Fresh start every Sunday - no manual tracking needed</span>
    </div>
</div>

<p class="message">
    <strong>Why Weekly Targets?</strong><br>
    While daily streaks are great for momentum, weekly targets provide a more balanced approach that accounts for life's realities. Missing one day doesn't break your progress - you can still achieve your weekly goal and maintain the habit.
</p>

<div class="button-container">
    <a href="{{ config('app.url') }}/dashboard" class="button">Check Your Weekly Progress</a>
</div>

<div class="alert alert-info">
    <span class="alert-title">Seamless Integration</span>
    <p class="alert-text">
        The weekly target widget now appears prominently on your dashboard alongside your existing streak counter. Both metrics work together to give you a complete picture of your reading consistency.
    </p>
</div>

<p class="message">
    This feature automatically tracks your reading logs - no additional setup required. Just keep logging your daily Bible reading and watch your weekly progress grow!
</p>

<hr class="divider">

<p class="message" style="margin-bottom: 0;">
    <strong>Happy reading!</strong><br>
    <em class="text-muted">The Delight Team</em><br>
    <span class="text-sm text-muted">Helping you delight in God's Word, one day at a time</span>
</p>
@endsection
