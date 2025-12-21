<?php

namespace App\Providers;

use App\Services\BibleReferenceService;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BibleReferenceService::class, function ($app) {
            return new BibleReferenceService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Response::macro('htmx', function ($view, $fragment = null, $data = []) {
            if (request()->header('HX-Request') && $fragment) {
                return response(view($view, $data)->fragment($fragment));
            }

            return response(view($view, $data));
        });
    }
}
