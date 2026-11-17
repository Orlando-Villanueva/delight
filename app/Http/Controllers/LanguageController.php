<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('localization.supported')))],
            'return_to' => ['required', 'string', 'starts_with:/', 'not_regex:~^//|\\\\~'],
        ]);

        return redirect($validated['return_to'])->withCookie(cookie(
            config('localization.cookie'), $validated['locale'], 525600,
        ));
    }
}
