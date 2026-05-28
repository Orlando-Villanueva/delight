<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? 'aes128gcm'
        );

        $user->forceFill([
            'push_notifications_enabled_at' => $user->push_notifications_enabled_at ?? now(),
            'daily_reading_reminder_enabled_at' => $user->daily_reading_reminder_enabled_at ?? now(),
            'streak_warning_enabled_at' => $user->streak_warning_enabled_at ?? now(),
            'push_notification_timezone' => $validated['timezone'] ?? $user->pushNotificationTimezone(),
            'reading_reminders_prompt_dismissed_at' => now(),
        ])->save();

        return response()->json([
            'enabled' => true,
            'subscription_count' => $user->pushSubscriptions()->count(),
        ]);
    }

    public function destroy(DeletePushSubscriptionRequest $request): Response
    {
        $request->user()->deletePushSubscription($request->validated('endpoint'));

        return response()->noContent();
    }
}
