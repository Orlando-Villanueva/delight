<?php

use App\Jobs\SendReadingReminderPush;
use App\Models\PushReminderDelivery;
use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingReminderPushNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('dispatch command creates due daily and streak reminder delivery rows once', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-26 18:05:00', 'America/Toronto'));
    $user = pushReminderUser();
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-05-25']);

    $this->artisan('push:dispatch-reading-reminders')
        ->expectsOutput('Reading reminder pushes queued: 2 due, 0 skipped.')
        ->assertSuccessful();

    $this->artisan('push:dispatch-reading-reminders')
        ->expectsOutput('Reading reminder pushes queued: 0 due, 2 skipped.')
        ->assertSuccessful();

    expect(PushReminderDelivery::query()->where('user_id', $user->id)->count())->toBe(2);

});

it('dispatch command uses subscription rows rather than the account connected marker', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $user = User::factory()->create([
        'push_notifications_enabled_at' => null,
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);
    $user->updatePushSubscription('https://example.com/subscription-'.$user->id, 'key', 'token', 'aes128gcm');

    $this->artisan('push:dispatch-reading-reminders')
        ->expectsOutput('Reading reminder pushes queued: 1 due, 0 skipped.')
        ->assertSuccessful();

    expect(PushReminderDelivery::query()->where('user_id', $user->id)->count())->toBe(1);

});

it('send job skips when user logged reading after delivery row was created', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $user = pushReminderUser();
    $delivery = PushReminderDelivery::factory()->create([
        'user_id' => $user->id,
        'reminder_type' => 'daily_reading',
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
    ]);

    ReadingLog::factory()->for($user)->create(['date_read' => '2026-05-26']);

    (new SendReadingReminderPush($delivery->id))->handle();

    Notification::assertNothingSent();
    expect($delivery->fresh()->skipped_at)->not->toBeNull();

});

it('send job skips stale delivery rows from a previous reminder date', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-27 09:05:00', 'America/Toronto'));
    $user = pushReminderUser();
    $delivery = PushReminderDelivery::factory()->create([
        'user_id' => $user->id,
        'reminder_type' => 'daily_reading',
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'),
    ]);

    (new SendReadingReminderPush($delivery->id))->handle();

    Notification::assertNothingSent();
    expect($delivery->fresh()->skipped_at)->not->toBeNull()
        ->and($delivery->fresh()->sent_at)->toBeNull();
});

it('send job lets notification send exceptions bubble for queue retry', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $user = pushReminderUser();
    $delivery = PushReminderDelivery::factory()->create([
        'user_id' => $user->id,
        'reminder_type' => 'daily_reading',
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
    ]);
    $exception = new RuntimeException('temporary push gateway outage');

    app()->instance(Dispatcher::class, new class($exception) implements Dispatcher
    {
        public function __construct(private RuntimeException $exception) {}

        public function send($notifiables, $notification): void
        {
            throw $this->exception;
        }

        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw $this->exception;
        }
    });

    expect(fn () => (new SendReadingReminderPush($delivery->id))->handle())
        ->toThrow(RuntimeException::class, 'temporary push gateway outage');

    expect($delivery->fresh()->failed_at)->toBeNull()
        ->and($delivery->fresh()->failure_reason)->toBeNull();
});

it('records exhausted notification send failures through the queue failed hook', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $delivery = PushReminderDelivery::factory()->create([
        'reminder_type' => 'daily_reading',
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
    ]);
    $exception = new RuntimeException(str_repeat('x', 300));

    (new SendReadingReminderPush($delivery->id))->failed($exception);

    $freshDelivery = $delivery->fresh();

    expect($freshDelivery->failed_at)->not->toBeNull()
        ->and($freshDelivery->failure_reason)->toHaveLength(255);
});

it('send job uses overlapping middleware keyed by delivery id', function () {
    $delivery = PushReminderDelivery::factory()->create();
    $job = new SendReadingReminderPush($delivery->id);

    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class)
        ->and($job->tries)->toBe(3)
        ->and($middleware[0]->releaseAfter)->toBe(30)
        ->and($middleware[0]->expiresAfter)->toBe(300);
});

it('notification target uses active plan route while copy stays generic', function () {
    $user = pushReminderUser();
    $plan = ReadingPlan::factory()->create(['slug' => 'daily-psalms']);
    $user->readingPlanSubscriptions()->create([
        'reading_plan_id' => $plan->id,
        'started_at' => today(),
        'is_active' => true,
    ]);

    $notification = new ReadingReminderPushNotification('daily_reading', '2026-05-26', route('plans.today', $plan));
    $message = $notification->toWebPush($user, $notification)->toArray();

    expect($notification->via($user))->toBe([WebPushChannel::class])
        ->and($message['title'])->toBe("Time for today's reading")
        ->and($message['body'])->toBe('Open Delight and log one chapter when you are ready.')
        ->and($message['data']['url'])->toBe(route('plans.today', $plan));
});

function pushReminderUser(): User
{
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);

    $user->updatePushSubscription('https://example.com/subscription-'.$user->id, 'key', 'token', 'aes128gcm');

    return $user;
}
