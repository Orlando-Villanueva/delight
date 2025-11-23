@extends('emails.layouts.base')

@section('title', 'Install Delight on Your Home Screen')

@section('content')
<h2 class="greeting">📱 Great news, {{ $user->name }}!</h2>

<p class="message">
    Delight now works like a mobile app when installed on your phone or tablet. 
    Get instant access to your Bible reading tracker right from your home screen!
</p>

<div class="alert alert-success">
    <span class="alert-title">🎉 New Feature Available</span>
    <p class="alert-text">
        Install Delight as a Progressive Web App - no app store needed!
    </p>
</div>

<div class="card">
    <span class="card-title">Why Install on Your Home Screen?</span>

    <div style="margin-bottom: 16px;">
        <strong style="color: #374151;">⚡ Instant Access:</strong>
        <span class="text-muted">No more hunting for browser tabs - tap the icon and you're in!</span>
    </div>

    <div style="margin-bottom: 16px;">
        <strong style="color: #374151;">🔥 Quick Streak Checking:</strong>
        <span class="text-muted">See your reading progress and maintain your streak faster than ever.</span>
    </div>

    <div style="margin-bottom: 16px;">
        <strong style="color: #374151;">📊 Faster Progress Updates:</strong>
        <span class="text-muted">Log your daily reading and view your book completion grid instantly.</span>
    </div>

    <div>
        <strong style="color: #374151;">📱 App-Like Experience:</strong>
        <span class="text-muted">Feels like a native app without taking up extra storage space.</span>
    </div>
</div>

<div class="alert alert-info">
    <span class="alert-title">📋 How to Install</span>
    <p class="alert-text">
        <strong>Important:</strong> You must visit Delight in your web browser first, then install from there.<br><br>
        <strong>iPhone/iPad (Safari):</strong> Tap the Share button (□↑) → "Add to Home Screen"<br>
        <strong>Android (Chrome):</strong> Tap the menu (⋮) → "Install app" or "Add to Home Screen"<br>
        <strong>Desktop (Chrome/Edge):</strong> Look for the install icon (⊞) in the address bar
    </p>
</div>

<div class="button-container">
    <a href="{{ url('/dashboard') }}" class="button">Open Delight & Install</a>
</div>

<p class="message">
    Once installed, Delight appears as an icon on your home screen. 
    Tap it anytime to quickly log your reading and keep your streak alive!
</p>

<div class="card">
    <span class="card-title">See It in Action</span>
    <p class="text-muted" style="margin: 0 0 16px 0;">
        Watch this quick tutorial showing how easy installation is:
    </p>
    
    <div class="text-center" style="margin: 16px 0;">
        <a href="https://twitter.com/SaintAriyel/status/1963013173403124047?ref_src=twsrc%5Etfw" style="display: inline-block; background: #1da1f2; color: white !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">
            🎥 Watch Tutorial Video
        </a>
    </div>
</div>

<hr class="divider">

<p class="message text-sm text-muted">
    This update makes building your Bible reading habit even more convenient. 
    Install today and never miss logging your daily progress!
</p>

<div class="card">
    <span class="card-title">Need More Help?</span>
    
    <div style="margin-bottom: 12px;">
        <strong style="color: #374151;">🟢 Chrome Guide:</strong>
        <a href="https://support.google.com/chrome/answer/9658361" class="link-primary">Install web apps</a>
    </div>
    
    <div>
        <strong style="color: #374151;">📖 General PWA Guide:</strong>
        <a href="https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Installing" class="link-primary">Installing PWAs (Mozilla)</a>
    </div>
</div>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ url('/dashboard') }}" class="link">Go to Dashboard</a> |
    <a href="{{ url('/logs') }}" class="link">View Reading History</a>
</p>
@endsection
