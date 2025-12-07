<?php

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\XOAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelightRewindController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ReadingLogController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Development Routes (Local Development Only)
if (app()->environment('local') || app()->environment('staging')) {
    Route::get('/telescope', function () {
        return redirect('/telescope/requests');
    });
}

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('landing');
})->name('landing');

// XML Sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Legal Pages
Route::get('/privacy-policy', function () {
    return view('legal.privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('legal.terms-of-service');
})->name('terms-of-service');

// Authentication Routes (GET routes for views - POST routes handled by Fortify)
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');

    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->name('password.request');

    Route::get('/reset-password/{token}', function ($token) {
        return view('auth.reset-password', ['request' => request()->merge(['token' => $token])]);
    })->name('password.reset');

    Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
        ->name('oauth.google.redirect');

    Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
        ->name('oauth.google.callback');

    Route::get('/auth/x/redirect', [XOAuthController::class, 'redirect'])->name('x.redirect');
    Route::get('/auth/x/callback', [XOAuthController::class, 'callback'])->name('x.callback');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    // Main Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reading Log Routes
    Route::get('/logs', [ReadingLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/create', [ReadingLogController::class, 'create'])->name('logs.create');
    Route::post('/logs', [ReadingLogController::class, 'store'])->name('logs.store');
    Route::patch('/logs/{readingLog}/notes', [ReadingLogController::class, 'updateNotes'])->name('logs.notes.update');
    Route::delete('/logs/batch', [ReadingLogController::class, 'batchDestroy'])->name('logs.batchDestroy');
    Route::delete('/logs/{readingLog}', [ReadingLogController::class, 'destroy'])->name('logs.destroy');

    // Feedback Routes
    Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');

    // Delight Rewind
    Route::get('/rewind', [DelightRewindController::class, 'index'])->name('rewind.index');
});
