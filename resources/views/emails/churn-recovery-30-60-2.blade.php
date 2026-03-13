@extends('emails.layouts.base')

@section('title', 'Take 60 seconds to get back into the habit')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    One small reading today is enough to restart the habit before more time slips by.
</p>

<p class="message">
    Open Delight, log a reading, and let the next streak begin with a single step.
</p>

<div class="button-container">
    <a href="{{ $ctaUrl }}" class="button">Log a Reading Now</a>
</div>

<p class="message">
    Grace and peace,<br>
    The Delight Team
</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">Stop receiving these emails</a>
</p>
@endsection
