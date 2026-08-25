<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleIdentityConflictException;
use App\Http\Controllers\Controller;
use App\Services\GoogleTokenExchangeService;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleOAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the OAuth callback and log the user in.
     */
    public function callback(Request $request, GoogleTokenExchangeService $exchangeService)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException|ClientException $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception_class' => $exception::class,
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'We could not complete Google sign in. Please try again or use your email and password.',
                ]);
        } catch (Throwable $exception) {
            Log::error('Unexpected Google OAuth callback failure.', [
                'exception_class' => $exception::class,
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'We hit an unexpected error while contacting Google. Please try again.',
                ]);
        }

        $googleEmail = $googleUser->getEmail();

        abort_if(blank($googleEmail), 422, 'Unable to complete sign in without an email address.');

        $googleDisplayName = $googleUser->getName() ?? $googleUser->getNickname() ?? $googleEmail;
        $googleAvatar = $googleUser->getAvatar();

        try {
            $user = $exchangeService->resolveWebUser(
                $googleEmail,
                $googleUser->getId(),
                $googleDisplayName,
                $googleAvatar,
            );
        } catch (GoogleIdentityConflictException) {
            Log::warning('Google OAuth callback rejected.', ['reason' => 'identity_conflict']);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'We could not complete Google sign in. Please use your existing sign-in method.',
                ]);
        }

        $updates = [];

        if ($googleDisplayName && $user->name !== $googleDisplayName) {
            $updates['name'] = $googleDisplayName;
        }

        if ($googleAvatar && $user->avatar_url !== $googleAvatar) {
            $updates['avatar_url'] = $googleAvatar;
        }

        if (! empty($updates)) {
            $user->forceFill($updates)->save();
        }

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->intended('/dashboard');
    }
}
