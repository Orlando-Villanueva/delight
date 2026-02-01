@extends('emails.layouts.base')

@section('title', 'Your Bible reading journey is waiting')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    We noticed it's been a while since you opened Delight. Life gets busy—we understand.
</p>

<p class="message">
    Your reading journey is still here, waiting for you. No pressure, no guilt. Just an open invitation to pick up where you left off.
</p>

<div class="button-container">
    <a href="{{ url('/dashboard') }}" class="button">Pick Up Where You Left Off</a>
</div>

<p class="message">
    Grace and peace,<br>
    The Delight Team
</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ url('/unsubscribe') }}" class="link">Unsubscribe from these emails</a>
</p>
@endsection
