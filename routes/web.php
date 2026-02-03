<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\AnnualRecapController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\XOAuthController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MarketingPreferencesController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PublicAnnouncementController;
use App\Http\Controllers\ReadingLogController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

// Development Routes (Local Development Only)
if (app()->environment('local') || app()->environment('staging')) {
    Route::get('/telescope', function () {
        return redirect('/telescope/requests');
    });

    Route::get('/dev/social-previews', function () {
        return view('dev.social-previews');
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

// Marketing Email Preferences (Signed URLs - no auth required)
Route::get('/marketing/unsubscribe/{user}', [MarketingPreferencesController::class, 'show'])
    ->middleware('signed')
    ->name('marketing.unsubscribe');

Route::post('/marketing/unsubscribe/{user}', [MarketingPreferencesController::class, 'store'])
    ->middleware('signed')
    ->name('marketing.unsubscribe.store');

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

    // Notifications (HTMX)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{announcement}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');

    // Annual Recap
    Route::get('/recap/{year?}', [AnnualRecapController::class, 'show'])->name('recap.show');

    // Reading Plans
    Route::get('/plans', [ReadingPlanController::class, 'index'])->name('plans.index');
    Route::post('/plans/{plan:slug}/subscribe', [ReadingPlanController::class, 'subscribe'])->name('plans.subscribe');
    Route::delete('/plans/{plan:slug}/unsubscribe', [ReadingPlanController::class, 'unsubscribe'])->name('plans.unsubscribe');

    // Legacy redirect for bookmarks
    Route::get('/plans/today', function () {
        $user = auth()->user();
        $activePlan = $user->activeReadingPlan();

        if ($activePlan && $activePlan->plan) {
            return redirect()->route('plans.today', $activePlan->plan);
        }

        return redirect()->route('plans.index');
    });

    Route::get('/plans/{plan:slug}/today', [ReadingPlanController::class, 'today'])->name('plans.today');
    Route::post('/plans/{plan:slug}/log-chapter', [ReadingPlanController::class, 'logChapter'])->name('plans.logChapter');
    Route::post('/plans/{plan:slug}/log-all', [ReadingPlanController::class, 'logAll'])->name('plans.logAll');
    Route::post('/plans/{plan:slug}/apply-readings', [ReadingPlanController::class, 'applyTodaysReadings'])
        ->name('plans.applyTodaysReadings');
    Route::post('/plans/{plan:slug}/activate', [ReadingPlanController::class, 'activate'])->name('plans.activate');

    // Onboarding
    Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])
        ->name('onboarding.dismiss');
});

// Admin Routes (Protected by check logic in middleware)
Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::post('announcements/preview', [AnnouncementController::class, 'preview'])
        ->name('announcements.preview');
    Route::resource('announcements', AnnouncementController::class)
        ->only(['index', 'create', 'store']);
});

// Public Announcements
Route::get('/updates', [PublicAnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/updates/{slug}', [PublicAnnouncementController::class, 'show'])->name('announcements.show');
