@extends('emails.layouts.base')

@section('title', 'Feedback Received')

@section('content')
<h2>Feedback Received</h2>

<p>
    <strong>User:</strong> {{ $data['user_name'] }} (ID: {{ $data['user_id'] }})<br>
    <strong>Email:</strong> {{ $data['user_email'] }}<br>
    <strong>Category:</strong> {{ ucfirst($data['category']) }}
</p>

<p>
    <strong>Message:</strong><br>
    <span style="white-space: pre-wrap;">{{ $data['message'] }}</span>
</p>

<p>
    Open app: {{ config('app.url') }}
</p>

<p>
    Sent from Delight Feedback System
</p>
@endsection
