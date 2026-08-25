<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            $email = $request->input('email');
            $normalizedEmail = is_string($email) ? Str::lower($email) : 'invalid-email';
            $throttleKey = Str::transliterate($normalizedEmail.'|'.$request->ip());

            $limit = app()->environment('local') ? 20 : 5;

            return Limit::perMinute($limit)->by($throttleKey);
        });

        RateLimiter::for('mobile-google-token', function (Request $request) {
            $limit = app()->environment('local') ? 20 : 5;

            return Limit::perMinute($limit)->by($request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
