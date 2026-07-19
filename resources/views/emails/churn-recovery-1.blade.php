@extends('emails.layouts.base')

@section('title', 'Keep your reading history up to date')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    It has been a week since your last reading log in Delight.
</p>

@if($lastReadingPassage)
<p class="message">
    Your most recent entry is <strong>{{ $lastReadingPassage }}</strong>.
</p>
@else
<p class="message">
    Your reading history is ready for your next entry.
</p>
@endif

<p class="message">
    If you have continued reading, add your latest entry so your progress stays current.
</p>

<div class="button-container">
    <a href="{{ $ctaUrl }}" class="button">Log a Reading</a>
</div>

<p class="message">— Delight</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">Unsubscribe from these emails</a>
</p>
@endsection
