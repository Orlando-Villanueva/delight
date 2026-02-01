@extends('emails.layouts.base')

@section('title', "Always here when you're ready")

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    We don't want to clutter your inbox, so this is the last email you'll see from us for a while.
</p>

<p class="message">
    We know seasons change. Whether you're busy, taking a break, or exploring other tools, we're cheering you on. Your reading history is safe and waiting whenever you feel like jumping back in.
</p>

<div class="button-container">
    <a href="{{ url('/dashboard') }}" class="button">Visit Delight</a>
</div>

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
