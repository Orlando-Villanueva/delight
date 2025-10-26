<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use ErrorException;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class XOAuthController extends Controller
{
    /**
     * Redirect the user to X's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('x')->redirect();
    }

    /**
     * Handle the OAuth callback from X and log the user in.
     */
    public function callback(Request $request)
    {
        try {
            $xUser = Socialite::driver('x')->user();
        } catch (InvalidStateException|ClientException $exception) {
            Log::warning('X OAuth callback failed.', [
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'state' => $request->get('state'),
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'We could not complete X sign in. Please try again or use your email and password.',
                ]);
        } catch (ErrorException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'confirmed_email')) {
                Log::info('X OAuth blocked user without confirmed email address.', [
                    'state' => $request->get('state'),
                ]);

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'oauth' => 'X requires you to confirm your email address before they will share it. Please confirm your email in X and try again.',
                    ]);
            }

            Log::error('Unexpected X OAuth error while retrieving user.', [
                'exception' => $exception,
                'state' => $request->get('state'),
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'We hit an unexpected error while contacting X. Please try again.',
                ]);
        } catch (Throwable $exception) {
            Log::error('Unexpected X OAuth callback failure.', [
                'exception' => $exception,
                'state' => $request->get('state'),
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'We hit an unexpected error while contacting X. Please try again.',
                ]);
        }

        $xEmail = $xUser->getEmail();

        if (blank($xEmail)) {
            Log::warning('X OAuth user missing email.', [
                'x_id' => $xUser->getId(),
                'nickname' => $xUser->getNickname(),
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'X requires you to confirm your email address before they will share it. Please confirm your email in X and try again.',
                ]);
        }

        $xDisplayName = $xUser->getName() ?? $xUser->getNickname() ?? $xEmail;
        $xAvatar = $xUser->getAvatar();

        $user = User::firstOrCreate(
            ['email' => $xEmail],
            [
                'name' => $xDisplayName,
                'password' => Hash::make(Str::random(64)),
                'avatar_url' => $xAvatar,
            ]
        );

        $updates = [];

        if ($xDisplayName && $user->name !== $xDisplayName) {
            $updates['name'] = $xDisplayName;
        }

        if ($xAvatar && $user->avatar_url !== $xAvatar) {
            $updates['avatar_url'] = $xAvatar;
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
