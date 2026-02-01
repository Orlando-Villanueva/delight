@extends('emails.layouts.base')

@section('title', 'Should we keep your account?')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    We've reached out a couple times, and we don't want to be a bother.
</p>

<p class="message">
    If Delight isn't right for you, that's okay. But if life just got in the way, we'd love to have you back.
</p>

<div class="button-container">
    <a href="{{ url('/dashboard') }}" class="button">I'm Back - Take Me to Delight</a>
</div>

<p class="message">
    Either way, we wish you well on your journey.
</p>

<p class="message">
    Grace and peace,<br>
    The Delight Team
</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ url('/unsubscribe') }}" class="link">Stop receiving these emails</a>
</p>
@endsection
