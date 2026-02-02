@extends('emails.layouts.base')

@section('title', 'No guilt, just grace - start fresh today')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    Building habits is hard. Missing days happens to everyone.
</p>

<p class="message">
    The beautiful thing about Scripture is it's always there when you're ready. Today could be day one of a new chapter in your journey.
</p>

<div class="button-container">
    <a href="{{ route('dashboard') }}" class="button">Start Fresh Today</a>
</div>

<p class="message" style="font-style: italic; text-align: center; color: #6b7280;">
    "His mercies are new every morning" - Lamentations 3:23
</p>

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
