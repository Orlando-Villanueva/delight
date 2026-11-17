<?php

use App\Http\Middleware\ApplyWebLocale;
use App\Http\Middleware\HandleReaderInertiaRequests;
use App\Http\Middleware\QueryLogger;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Services\LocaleResolver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
        ]);

        // Browser JavaScript writes this timezone report without Laravel's cookie encryption.
        $middleware->encryptCookies(except: ['reading_timezone_report']);

        $middleware->validateCsrfTokens(except: [
            'marketing/unsubscribe/*/one-click',
        ]);

        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
            ApplyWebLocale::class,
            HandleReaderInertiaRequests::class,
            QueryLogger::class,
        ]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, HandleReaderInertiaRequests::class);
        $middleware->prependToPriorityList(HandleReaderInertiaRequests::class, ApplyWebLocale::class);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['expo_push_token']);
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof ValidationException || $exception instanceof AuthenticationException || $exception instanceof HttpResponseException) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*', 'admin', 'admin/*', 'telescope', 'telescope/*', 'recap', 'recap/*', 'updates', 'updates/*', 'android', 'account-deletion', 'account-deletion/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            if (! in_array($status, [403, 404, 419, 429, 500, 503], true) || ($status === 500 && config('app.debug'))) {
                return null;
            }

            $render = function (Request $request) use ($exception, $status) {
                $previousLocale = app()->getLocale();
                app()->setLocale(app(LocaleResolver::class)->resolve($request));
                try {
                    $view = in_array($status, [403, 404, 500], true) ? 'errors.'.$status : 'errors.standard';
                    $response = response()->view($view, ['status' => $status], $status, $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []);
                    $response->headers->set('Content-Language', app()->getLocale());
                    $response->setVary(['Accept-Language', 'Cookie'], false);

                    return $response;
                } finally {
                    app()->setLocale($previousLocale);
                }
            };

            if (! $request->hasSession()) {
                return app(EncryptCookies::class)->handle($request, fn (Request $request) => app(StartSession::class)->handle($request, $render));
            }

            return $render($request);
        });
        Integration::handles($exceptions);
    })->create();
