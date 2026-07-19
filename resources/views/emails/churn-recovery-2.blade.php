@extends('emails.layouts.base')

@section('title', 'A quick check-in from Delight')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    Your reading history is more useful when it stays current.
</p>

@if($lastReadingPassage)
<p class="message">
    Your most recent entry is still <strong>{{ $lastReadingPassage }}</strong>.
</p>
@endif

<p class="message">
    Logging your latest reading keeps your progress, notes, and milestones together without having to reconstruct them later.
</p>

<div class="button-container">
    <a href="{{ $ctaUrl }}" class="button">Update My Reading Log</a>
</div>

<p class="message">— Delight</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">Unsubscribe from these emails</a>
</p>
@endsection
