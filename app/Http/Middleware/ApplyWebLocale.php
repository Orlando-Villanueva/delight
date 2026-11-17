<?php

namespace App\Http\Middleware;

use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyWebLocale
{
    public function __construct(private LocaleResolver $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*', 'telescope', 'telescope/*', 'recap', 'recap/*', 'updates', 'updates/*', 'android', 'account-deletion', 'account-deletion/*')) {
            return $next($request);
        }

        $previousLocale = app()->getLocale();
        $user = $request->user();
        $shouldInitialize = $user && $user->preferred_locale === null && $request->isMethod('GET');
        $locale = $this->locales->resolve($request);
        app()->setLocale($locale);

        try {
            $response = $next($request);
            if ($shouldInitialize && $response->isSuccessful()) {
                $established = $this->locales->establishLocale($user, $request);
                if ($established !== $locale) {
                    $response = redirect($request->fullUrl());
                    app()->setLocale($established);
                }
            }

            $response->headers->set('Content-Language', app()->getLocale());
            $response->setVary(['Accept-Language', 'Cookie'], false);

            if (! $user && $request->routeIs('password.reset')) {
                $hint = $this->locales->normalize($request->query('locale'));

                if ($hint !== null) {
                    $response->headers->setCookie(cookie(config('localization.cookie'), $hint, 525600));
                }
            }

            return $response;
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
