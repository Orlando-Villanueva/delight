<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
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
        $request->user()->forceFill([
            'deuterocanonical_books_enabled_at' => $request->boolean('include_deuterocanonical') ? now() : null,
        ])->save();

        Cache::forget("user_dashboard_stats_{$request->user()->id}");

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
