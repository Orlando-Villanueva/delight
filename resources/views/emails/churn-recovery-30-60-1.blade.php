@extends('emails.layouts.base')

@section('title', 'Restart with one simple reading today')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    A month away can turn into two before you notice it, so we wanted to make restarting easy.
</p>

@if($lastReadingPassage)
<p class="message">
    Your last reading was <strong>{{ $lastReadingPassage }}</strong>. Pick up with one quick reading and rebuild the rhythm from there.
</p>
@else
<p class="message">
    You do not need a big reset plan. One short reading is enough to get back on track today.
</p>
@endif

<div class="button-container">
    <a href="{{ $ctaUrl }}" class="button">Log Your Next Reading</a>
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
