@extends('emails.layouts.base')

@section('title', __('A quick check-in from Delight'))

@section('content')
<h2 class="greeting">{{ __('Hi') }} {{ $user->name ?? 'there' }},</h2>

<p class="message">
    {{ __("Your reading history is more useful when it stays current.") }}
</p>

@if($lastReadingPassage)
<p class="message">
    {{ __("Your most recent entry is still") }} <strong>{{ $lastReadingPassage }}</strong>.
</p>
@endif

<p class="message">
    {{ __("Logging your latest reading keeps your progress, notes, and milestones together without having to reconstruct them later.") }}
</p>

<div class="button-container">
    <a href="{{ $ctaUrl }}" class="button">{{ __("Update My Reading Log") }}</a>
</div>

<p class="message">— Delight</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">{{ __("Unsubscribe from these emails") }}</a>
</p>
@endsection
