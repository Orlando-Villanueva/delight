@extends('emails.layouts.base')

@section('title', $announcement->title)

@section('content')
<h1 class="greeting">{{ $announcement->title }}</h1>

@if ($heroImageUrl)
    <div class="card">
        <img src="{{ $heroImageUrl }}" alt="{{ $announcement->title }}" style="display: block; width: 100%; height: auto; border-radius: 12px;">
    </div>
@endif

<div class="message">
    {!! Str::markdown($announcement->content) !!}
</div>

<div class="button-container">
    <a href="{{ $announcementUrl }}" class="button">Read this update</a>
</div>
@endsection

@section('footer-extra')
<p class="footer-text">
    <a href="{{ $unsubscribeUrl }}" class="link">Unsubscribe from these emails</a>
</p>
@endsection
