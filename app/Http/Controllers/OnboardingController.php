<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    private OnboardingService $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    /**
     * Dismiss the onboarding flow for the authenticated user.
     */
    public function dismiss(Request $request)
    {
        $this->onboardingService->dismiss($request->user());

        return response()->noContent();
    }

    /**
     * Dismiss onboarding and optionally schedule a reminder for tomorrow.
     */
    public function remind(Request $request)
    {
        $this->onboardingService->remind($request->user()->id);

        return response()->noContent();
    }
}
