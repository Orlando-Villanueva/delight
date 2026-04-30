<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\AnnualRecapService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Show account settings.
     */
    public function edit(): View
    {
        return view('settings.edit');
    }

    /**
     * Update account settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $wasIncludingDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $shouldIncludeDeuterocanonical = $request->boolean('include_deuterocanonical');

        $user->forceFill([
            'deuterocanonical_books_enabled_at' => $shouldIncludeDeuterocanonical ? now() : null,
        ])->save();

        Cache::forget("user_dashboard_stats_{$user->id}");

        if ($wasIncludingDeuterocanonical !== $shouldIncludeDeuterocanonical) {
            Cache::forget(AnnualRecapService::cacheKeyFor($user, now()->year));
        }

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
