<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

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
        $googleUser = Socialite::driver('google')->user();
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
