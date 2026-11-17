<?php

namespace App\Exceptions;

use App\Services\LocaleResolver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LocalizedWebExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?Response
    {
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        if (! $this->supports($exception, $request, $status)) {
            return null;
        }

        $render = fn (Request $request): Response => $this->renderLocalized($exception, $request, $status);
        if (! $request->hasSession()) {
            return app(EncryptCookies::class)->handle($request, fn (Request $request) => app(StartSession::class)->handle($request, $render));
        }

        return $render($request);
    }

    private function supports(Throwable $exception, Request $request, int $status): bool
    {
        return ! ($exception instanceof ValidationException || $exception instanceof AuthenticationException || $exception instanceof HttpResponseException)
            && ! $request->expectsJson()
            && ! $request->is('api/*', 'admin', 'admin/*', 'telescope', 'telescope/*', 'recap', 'recap/*', 'updates', 'updates/*', 'android', 'account-deletion', 'account-deletion/*')
            && in_array($status, [403, 404, 419, 429, 500, 503], true)
            && ! ($status === 500 && config('app.debug'));
    }

    private function renderLocalized(Throwable $exception, Request $request, int $status): Response
    {
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
    }
}
