@extends('emails.layouts.base')

@section('title', 'Your Bible reading journey is waiting')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    We noticed it's been a while since you opened Delight. Life gets busy—we understand.
</p>

@if($lastReadingPassage)
<p class="message">
    Your last reading was <strong>{{ $lastReadingPassage }}</strong>. Pick up where you left off—your journey is waiting.
</p>
@else
<p class="message">
    Your reading journey is still here, waiting for you. No pressure, no guilt. Just an open invitation to pick up where you left off.
</p>
@endif

<div class="button-container">
    <a href="{{ route('dashboard') }}" class="button">Pick Up Where You Left Off</a>
</div>

<p class="message">
    Grace and peace,<br>
    The Delight Team
</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">Unsubscribe from these emails</a>
</p>
@endsection
