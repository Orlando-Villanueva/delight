@extends('emails.layouts.base')

@section('title', __('A gentle reminder from Delight'))

@section('content')
<h2 class="greeting">{{ __('Hi') }} {{ $user->name ?? 'there' }},</h2>

<p class="message">
    {{ __("Just a gentle nudge for your first reading in Delight. Starting small builds momentum.") }}
</p>

<div class="card">
    <div class="card-heading">
        <span class="card-icon">📖</span>
        <h3 class="card-title">{{ __("Try John 1 to get started") }}</h3>
    </div>
    <p class="card-text">
        {{ __("A single chapter is enough. Log it in Delight and your journey begins.") }}
    </p>
</div>

<div class="button-container">
    <a href="{{ route('logs.create') }}" class="button">{{ __("Log Your First Reading") }}</a>
</div>

<p class="message">
    {{ __("Grace and peace,") }}<br>
    {{ __("The Delight Team") }}
</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">{{ __("Unsubscribe from these emails") }}</a>
</p>
@endsection
