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

            return view($view, $data);
        });

        // Share unread notifications count with authenticated layout
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            if (auth()->check()) {
                // Optimization: We could cache this or share it more selectively
                // But for this scale, a simple count query is fine.
                // We only bind it if the view seems to be a main page.
                // Actually, View::share is better if we want it everywhere.
                // But we can just use a closure variable that lazily evaluates?
                // Let's just bind 'unreadAnnouncementCount' if auth check passes.

                // However, running this query on EVERY partial render (HTMX) is bad.
                // We should limit this to the layout or the specific component.
            }
        });

        \Illuminate\Support\Facades\View::composer('components.ui.notification-bell', function ($view) {
            if (auth()->check()) {
                $view->with('unreadCount', auth()->user()->unreadAnnouncements()->count());
            }
        });
    }
}
