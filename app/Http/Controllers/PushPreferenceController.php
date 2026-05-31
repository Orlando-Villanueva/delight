<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePushPreferencesRequest;
use Illuminate\Http\JsonResponse;

class PushPreferenceController extends Controller
{
    public function update(UpdatePushPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->forceFill([
            'daily_reading_reminder_enabled_at' => array_key_exists('daily_reading_reminder_enabled', $validated)
                ? ($request->boolean('daily_reading_reminder_enabled') ? now() : null)
                : $user->daily_reading_reminder_enabled_at,
            'streak_warning_enabled_at' => array_key_exists('streak_warning_enabled', $validated)
                ? ($request->boolean('streak_warning_enabled') ? now() : null)
                : $user->streak_warning_enabled_at,
            'push_notification_timezone' => $validated['timezone'] ?? $user->pushNotificationTimezone(),
        ])->save();

        $freshUser = $user->fresh();

        return response()->json([
            'enabled' => $freshUser->pushSubscriptions()->exists(),
            'account_has_devices' => $freshUser->pushSubscriptions()->exists(),
            'daily_reading_reminder_enabled' => $freshUser->hasDailyReadingReminderEnabled(),
            'streak_warning_enabled' => $freshUser->hasStreakWarningEnabled(),
            'push_notification_timezone' => $freshUser->pushNotificationTimezone(),
        ]);
    }
}
