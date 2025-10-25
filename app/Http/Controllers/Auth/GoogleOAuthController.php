<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException|ClientException $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'state' => request()->get('state'),
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'We could not complete Google sign in. Please try again or use your email and password.',
                ]);
        } catch (Throwable $exception) {
            Log::error('Unexpected Google OAuth callback failure.', [
                'exception' => $exception,
                'state' => request()->get('state'),
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

        $user = User::firstOrCreate(
            ['email' => $googleEmail],
            [
                'name' => $googleDisplayName,
                'password' => Hash::make(Str::random(64)),
                'avatar_url' => $googleAvatar,
            ]
        );

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

        return redirect()->intended('/dashboard');
    }
}
