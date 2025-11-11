<?php

use App\Enums\WeeklyJourneyDayState;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\XOAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReadingLogController;
use App\Http\Controllers\SitemapController;
use Carbon\Carbon;
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

Route::get('/demo/weekly-journey', function () {
    $weekStart = Carbon::create(2025, 11, 9)->startOfWeek(Carbon::SUNDAY);
    $weekEnd = $weekStart->copy()->addDays(6);
    $weeklyTarget = 7;
    $weekRangeText = sprintf('%s–%s', $weekStart->format('M j'), $weekEnd->format('j'));

    $buildDays = function (int $todayIndex, array $readIndexes = []) use ($weekStart) {
        $today = $weekStart->copy()->addDays($todayIndex);

        return collect(range(0, 6))
            ->map(function ($offset) use ($weekStart, $readIndexes, $today) {
                $date = $weekStart->copy()->addDays($offset);
                $isRead = in_array($offset, $readIndexes, true);
                $state = WeeklyJourneyDayState::resolve($date, $today, $isRead)->value;
                $label = sprintf(
                    '%s — %s',
                    $date->format('D M j'),
                    match ($state) {
                        'complete' => 'reading logged',
                        'missed' => 'missed reading day',
                        'today' => 'today (not logged yet)',
                        default => 'not logged yet',
                    }
                );

                return [
                    'date' => $date->toDateString(),
                    'dow' => $date->dayOfWeek,
                    'isToday' => $date->isSameDay($today),
                    'read' => $isRead,
                    'state' => $state,
                    'title' => $label,
                    'ariaLabel' => $label,
                ];
            })
            ->all();
    };

    $statusTone = function (string $microcopy, string $classes = 'text-gray-600 dark:text-gray-300') {
        return [
            'state' => null,
            'label' => null,
            'microcopy' => $microcopy,
            'chipClasses' => '',
            'microcopyClasses' => $classes,
            'showCrown' => false,
        ];
    };

    $perfectStatus = [
        'state' => 'perfect',
        'label' => 'Perfect week',
        'microcopy' => 'You did it—enjoy some rest!',
        'chipClasses' => 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-900/40 dark:text-amber-100 dark:border-amber-800',
        'microcopyClasses' => 'text-amber-600 dark:text-amber-300 font-semibold',
        'showCrown' => true,
    ];

    $makeVariant = function (string $title, string $description, array $config = []) use ($buildDays, $weekRangeText, $weeklyTarget) {
        $todayIndex = $config['todayIndex'] ?? 0;
        $readIndexes = $config['readIndexes'] ?? [];
        $currentProgress = $config['currentProgress'] ?? count(array_unique($readIndexes));
        $days = $buildDays($todayIndex, $readIndexes);
        $todayRead = in_array($todayIndex, $readIndexes, true);
        $ctaVisible = $config['ctaVisible'] ?? (! $todayRead && $currentProgress < $weeklyTarget);

        return [
            'title' => $title,
            'description' => $description,
            'props' => [
                'currentProgress' => $currentProgress,
                'days' => $days,
                'weekRangeText' => $config['weekRangeText'] ?? $weekRangeText,
                'weeklyTarget' => $weeklyTarget,
                'ctaEnabled' => $config['ctaEnabled'] ?? true,
                'ctaVisible' => $ctaVisible,
                'status' => $config['status'] ?? null,
                'journeyAltText' => $config['journeyAltText'] ?? null,
            ],
        ];
    };

    $variants = [
        $makeVariant('Fresh Sunday (empty)', 'Brand-new week with no logs yet; CTA encourages a strong start.', [
            'todayIndex' => 0,
            'readIndexes' => [],
            'status' => $statusTone('Kick off your week'),
        ]),
        $makeVariant('Midweek momentum', 'Three days logged, today still open to keep pace.', [
            'todayIndex' => 3,
            'readIndexes' => [0, 1, 2],
            'status' => $statusTone('Nice start—keep going', 'text-primary-700 dark:text-primary-200'),
        ]),
        $makeVariant('Midweek logged today', 'Today is already logged, so the CTA stays hidden until tomorrow.', [
            'todayIndex' => 3,
            'readIndexes' => [0, 1, 2, 3],
            'status' => $statusTone('Already logged today—nice work!', 'text-success-700 dark:text-success-200'),
            'ctaVisible' => false,
        ]),
        $makeVariant('Saturday catch-up (2 of 7)', 'Late-week reminder when only a couple of days are logged.', [
            'todayIndex' => 6,
            'readIndexes' => [0, 2],
            'status' => $statusTone('There\'s still time this week', 'text-amber-600 dark:text-amber-300'),
        ]),
        $makeVariant('Saturday push (5 of 7)', 'Closing in on the goal with two more sessions needed.', [
            'todayIndex' => 5,
            'readIndexes' => [0, 1, 2, 3, 4],
            'status' => $statusTone('Two more to go!', 'text-success-700 dark:text-success-200'),
        ]),
        $makeVariant('Saturday wind-down (today logged)', 'Today has already been logged even though the week isn’t perfect.', [
            'todayIndex' => 6,
            'readIndexes' => [0, 1, 2, 5, 6],
            'status' => $statusTone('Great job today—rest up!', 'text-primary-700 dark:text-primary-200'),
            'ctaVisible' => false,
        ]),
        $makeVariant('Perfect week celebration', 'Seven days logged—the badge and crown are visible.', [
            'todayIndex' => 6,
            'readIndexes' => range(0, 6),
            'status' => $perfectStatus,
            'ctaVisible' => false,
        ]),
    ];

    return view('demo.weekly-journey', compact('variants'));
})->name('demo.weekly-journey');

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
});
