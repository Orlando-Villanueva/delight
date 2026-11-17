@extends('emails.layouts.base')

@section('title', __('Reset Your Password - Delight'))

@section('content')
    <h2 class="greeting">{{ __("Reset Your Password") }}</h2>
    
    <p class="message">
        {{ __("Hello! We received a request to reset your password for your Delight account.") }}
        {{ __("Click the button below to create a new password and get back to building your Bible reading habit.") }}
    </p>

    <div class="button-container">
        <a href="{{ $resetUrl }}" class="button">{{ __("Reset My Password") }}</a>
    </div>

    <p class="message">
        {{ __('This password reset link will expire in') }} {{ config('auth.passwords.users.expire', 60) }} {{ __('minutes for your security.') }}
    </p>

    <div class="alert alert-warning">
        <span class="alert-title">🔒 {{ __('Security Notice') }}</span>
        <p class="alert-text">
            {{ __("If you didn't request this password reset, you can safely ignore this email. Your password will remain unchanged.") }}
        </p>
    </div>

    <hr class="divider">
    
    <p class="message text-sm text-muted">
        {{ __("If you're having trouble clicking the button, copy and paste the URL below into your web browser:") }}
    </p>
    <p class="text-sm">
        <a href="{{ $resetUrl }}" class="link-primary" style="word-break: break-all;">{{ $resetUrl }}</a>
    </p>
@endsection
