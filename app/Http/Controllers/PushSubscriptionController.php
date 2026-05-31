<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\PushSubscriptionStatusRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function status(PushSubscriptionStatusRequest $request): JsonResponse
    {
        return response()->json($this->subscriptionState(
            $request->user(),
            $request->validated('endpoint')
        ));
    }

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

        return response()->json($this->subscriptionState($user->fresh(), $validated['endpoint']));
    }

    public function destroy(DeletePushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        $endpoint = $request->validated('endpoint');

        $user->deletePushSubscription($endpoint);
        $this->clearConnectedMarkerWhenEmpty($user);

        return response()->json($this->subscriptionState($user->fresh(), $endpoint));
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->pushSubscriptions()->delete();
        $user->forceFill([
            'push_notifications_enabled_at' => null,
        ])->save();

        return response()->json($this->subscriptionState($user->fresh()));
    }

    /**
     * @return array{device_enabled: bool, account_has_devices: bool, subscription_count: int, daily_reading_reminder_enabled: bool, streak_warning_enabled: bool, push_notification_timezone: string}
     */
    private function subscriptionState(User $user, ?string $endpoint = null): array
    {
        $subscriptionCount = $user->pushSubscriptions()->count();

        return [
            'device_enabled' => $endpoint !== null && $user->pushSubscriptions()->where('endpoint', $endpoint)->exists(),
            'account_has_devices' => $subscriptionCount > 0,
            'subscription_count' => $subscriptionCount,
            'daily_reading_reminder_enabled' => $user->hasDailyReadingReminderEnabled(),
            'streak_warning_enabled' => $user->hasStreakWarningEnabled(),
            'push_notification_timezone' => $user->pushNotificationTimezone(),
        ];
    }

    private function clearConnectedMarkerWhenEmpty(User $user): void
    {
        if ($user->pushSubscriptions()->exists()) {
            return;
        }

        $user->forceFill([
            'push_notifications_enabled_at' => null,
        ])->save();
    }
}
