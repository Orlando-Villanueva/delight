@extends('emails.layouts.base')

@section('title', 'New Feedback Received')

@section('content')
<h2 class="greeting">New Feedback Received</h2>

<div class="card">
    <div style="margin-bottom: 12px;">
        <strong>User:</strong> <span class="text-muted">{{ $data['user_name'] }} (ID: {{ $data['user_id'] }})</span>
    </div>
    <div style="margin-bottom: 12px;">
        <strong>Email:</strong> <a href="mailto:{{ $data['user_email'] }}" class="link-primary">{{ $data['user_email'] }}</a>
    </div>
    <div>
        <strong>Category:</strong> <span class="text-muted">{{ ucfirst($data['category']) }}</span>
    </div>
</div>

<div class="alert alert-info">
    <span class="alert-title">Message Content</span>
    <p class="alert-text" style="white-space: pre-wrap;">{{ $data['message'] }}</p>
</div>

<div class="button-container">
    <a href="{{ config('app.url') }}" class="button">Open App</a>
</div>

<p class="message text-sm text-muted text-center">
    Sent from Delight Feedback System
</p>
@endsection
