<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Dismiss the onboarding flow for the authenticated user.
     */
    public function dismiss(Request $request)
    {
        $request->user()->update([
            'onboarding_dismissed_at' => now(),
        ]);

        return response()->noContent();
    }
}
