<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MarketingPreferencesController extends Controller
{
    /**
     * Show the unsubscribe confirmation page.
     *
     * Signed URL ensures only the intended recipient can access this.
     */
    public function show(Request $request, User $user)
    {
        return view('marketing.unsubscribe', [
            'user' => $user,
            'isOptedOut' => ! is_null($user->marketing_emails_opted_out_at),
        ]);
    }

    /**
     * Process the unsubscribe request.
     *
     * Updates the user's marketing_emails_opted_out_at timestamp.
     */
    public function store(Request $request, User $user)
    {
        if (is_null($user->marketing_emails_opted_out_at)) {
            $user->update(['marketing_emails_opted_out_at' => now()]);
        }

        return back()->with('status', 'You have been unsubscribed from marketing emails.');
    }
}
