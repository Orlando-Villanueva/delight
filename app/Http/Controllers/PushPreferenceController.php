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

        if (array_key_exists('push_notifications_enabled', $validated) && ! $request->boolean('push_notifications_enabled')) {
            $user->pushSubscriptions()->delete();
            $user->forceFill([
                'push_notifications_enabled_at' => null,
                'daily_reading_reminder_enabled_at' => null,
                'streak_warning_enabled_at' => null,
            ])->save();

            return response()->json(['enabled' => false]);
        }

        $user->forceFill([
            'daily_reading_reminder_enabled_at' => array_key_exists('daily_reading_reminder_enabled', $validated)
                ? ($request->boolean('daily_reading_reminder_enabled') ? now() : null)
                : $user->daily_reading_reminder_enabled_at,
            'streak_warning_enabled_at' => array_key_exists('streak_warning_enabled', $validated)
                ? ($request->boolean('streak_warning_enabled') ? now() : null)
                : $user->streak_warning_enabled_at,
            'push_notification_timezone' => $validated['timezone'] ?? $user->pushNotificationTimezone(),
        ])->save();

        return response()->json([
            'enabled' => $user->fresh()->hasPushNotificationsEnabled(),
        ]);
    }
}
