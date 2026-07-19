@extends('emails.layouts.base')

@section('title', 'Add your latest reading to Delight')

@section('content')
<h2 class="greeting">Hi {{ $user->name ?? 'there' }},</h2>

<p class="message">
    This is the final email in this reading-log check-in series.
</p>

<p class="message">
    Your existing reading history will remain available. If you have continued reading, you can add your latest entry now.
</p>

<div class="button-container">
    <a href="{{ $ctaUrl }}" class="button">Log a Reading</a>
</div>

<p class="message">— Delight</p>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">Stop receiving these emails</a>
</p>
@endsection
