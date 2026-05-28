<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DashboardReminderPromptController extends Controller
{
    public function dismiss(): Response
    {
        auth()->user()->forceFill([
            'reading_reminders_prompt_dismissed_at' => now(),
        ])->save();

        return response()->noContent();
    }
}
