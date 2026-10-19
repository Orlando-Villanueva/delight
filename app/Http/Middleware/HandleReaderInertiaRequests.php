<?php

namespace App\Http\Middleware;

use App\Services\ReadingPlanService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleReaderInertiaRequests extends Middleware
{
    protected $rootView = 'layouts.reader-app';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('settings', 'logs/create')) {
            return $next($request);
        }

        if ($request->isMethod('GET') && $request->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', $request->url());
        }

        if (! $request->user() && $request->header('X-Inertia')) {
            $request->session()->put('url.intended', $request->url());

            return Inertia::location(route('login'));
        }

        Inertia::encryptHistory();
        $response = parent::handle($request, $next);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary(['HX-Request', 'X-Inertia'], false);

        return $response;
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'status' => fn () => $request->session()->get('status'),
            'reader' => fn () => $user ? [
                'appName' => config('app.name'),
                'logoUrl' => asset('images/logo-64.png').'?v='.config('app.asset_version'),
                'user' => ['name' => $user->name, 'email' => $user->email, 'avatar' => $user->avatar_url],
                'csrfToken' => csrf_token(),
                'unreadCount' => $user->unreadAnnouncements()->count(),
                'urls' => [
                    'dashboard' => route('dashboard'), 'plans' => app(ReadingPlanService::class)->getSmartPlansUrl($user),
                    'log' => route('logs.create'), 'history' => route('logs.index'),
                    'settings' => route('settings.edit'), 'achievements' => route('achievements.index'),
                    'recap' => route('recap.show'), 'feedback' => route('feedback.create'),
                    'notifications' => route('notifications.index'), 'logout' => route('logout'),
                    'admin' => $user->isAdmin() ? route('filament.admin.pages.admin-home') : null,
                ],
            ] : null,
        ]);
    }
}
