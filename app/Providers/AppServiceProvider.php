<?php

namespace App\Providers;

use App\Services\BibleReferenceService;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
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

            return view($view, $data);
        });

        View::composer('components.ui.notification-bell', function ($view) {
            if (auth()->check()) {
                $view->with('unreadCount', auth()->user()->unreadAnnouncements()->count());
            }
        });

        // Smart routing for Reading Plans navigation
        // Users with active plans go directly to today's reading
        View::composer(['components.navigation.mobile-bottom-bar', 'components.navigation.desktop-sidebar'], function ($view) {
            $smartPlansRoute = 'plans.index';
            if (auth()->check() && auth()->user()->activeReadingPlan()) {
                $smartPlansRoute = 'plans.today';
            }
            $view->with('smartPlansRoute', $smartPlansRoute);
        });
    }
}
