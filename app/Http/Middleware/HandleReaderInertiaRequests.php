<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
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
        if (! $request->is('dashboard', 'feedback', 'settings', 'logs/create', 'logs', 'logs/*', 'achievements', 'plans', 'plans/*', 'onboarding/*')) {
            return $next($request);
        }

        if ($request->isMethod('GET') && $request->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', $request->fullUrl());
        }

        if (! $request->user() && $request->header('X-Inertia')) {
            $request->session()->put('url.intended', $request->url());

            return Inertia::location(route('login'));
        }

        Inertia::encryptHistory();
        $response = parent::handle($request, $next);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary(['Accept', 'HX-Request', 'X-Inertia'], false);

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
                'announcements' => Announcement::visible()
                    ->select(['id', 'slug', 'title', 'starts_at'])
                    ->withExists(['users as is_read' => fn ($query) => $query->where('user_id', $user->id)])
                    ->latest('starts_at')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (Announcement $announcement): array => [
                        'id' => $announcement->id,
                        'title' => $announcement->title,
                        'url' => route('announcements.show', $announcement->slug),
                        'date' => $announcement->starts_at?->toIso8601String(),
                        'dateLabel' => $announcement->starts_at?->diffForHumans(),
                        'isRead' => (bool) $announcement->is_read,
                    ]),
                'urls' => [
                    'dashboard' => route('dashboard'), 'plans' => app(ReadingPlanService::class)->getSmartPlansUrl($user),
                    'log' => route('logs.create'), 'history' => route('logs.index'),
                    'settings' => route('settings.edit'), 'achievements' => route('achievements.index'),
                    'recap' => route('recap.show'), 'feedback' => route('feedback.create'),
                    'updates' => route('announcements.index'), 'logout' => route('logout'),
                    'admin' => $user->isAdmin() ? route('filament.admin.pages.admin-home') : null,
                ],
            ] : null,
        ]);
    }
}
