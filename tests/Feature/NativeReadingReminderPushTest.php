<?php

use App\Enums\NativePushReceiptStatus;
use App\Jobs\SendNativeReadingReminderPushBatch;
use App\Models\NativePushRegistration;
use App\Models\NativePushReminderDelivery;
use App\Models\NativeReminderPreference;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ExpoPushService;
use App\Services\ReadingReminderConditionService;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('sends Expo messages in a bounded batch and indexes receipts by ticket id', function (): void {
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [
                ['status' => 'ok', 'id' => 'ticket-1'],
                ['status' => 'ok', 'id' => 'ticket-2'],
            ],
        ]),
        'https://exp.host/--/api/v2/push/getReceipts' => Http::response([
            'data' => [
                'ticket-1' => ['status' => 'ok'],
                'ticket-2' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
            ],
        ]),
    ]);

    $expo = app(ExpoPushService::class);
    $tickets = $expo->send([
        ['to' => 'ExpoPushToken[first]'],
        ['to' => 'ExpoPushToken[second]'],
    ]);
    $receipts = $expo->receipts(['ticket-1', 'ticket-2']);

    expect($tickets)->toHaveCount(2)
        ->and($receipts['ticket-2']['details']['error'])->toBe('DeviceNotRegistered');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://exp.host/--/api/v2/push/send'
            && count($request->data()) === 2
            && $request->data()[0]['to'] === 'ExpoPushToken[first]';
    });
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://exp.host/--/api/v2/push/getReceipts'
            && $request->data()['ids'] === ['ticket-1', 'ticket-2'];
    });
});

it('retries transient Expo failures but rejects oversized batches', function (): void {
    Http::fakeSequence('https://exp.host/--/api/v2/push/send')
        ->pushStatus(503)
        ->push(['data' => [['status' => 'ok', 'id' => 'ticket-1']]]);

    expect(app(ExpoPushService::class)->send([['to' => 'ExpoPushToken[first]']]))
        ->toBe([['status' => 'ok', 'id' => 'ticket-1']]);

    Http::assertSentCount(2);

    expect(fn () => app(ExpoPushService::class)->send(array_fill(0, 101, ['to' => 'ExpoPushToken[x]'])))
        ->toThrow(RuntimeException::class, 'at most 100 messages');
});

it('queues one native delivery for each enabled device and remains idempotent', function (): void {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-26 18:05:00', 'America/Toronto'));
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-05-25']);
    nativeReminderDevice($user, 'ExpoPushToken[phone]');
    nativeReminderDevice($user, 'ExpoPushToken[tablet]');

    $this->artisan('push:dispatch-native-reading-reminders')
        ->expectsOutput('Native reading reminder pushes queued: 4 due, 0 skipped.')
        ->assertSuccessful();

    $this->artisan('push:dispatch-native-reading-reminders')
        ->expectsOutput('Native reading reminder pushes queued: 0 due, 4 skipped.')
        ->assertSuccessful();

    expect(NativePushReminderDelivery::query()->count())->toBe(4);
    Queue::assertPushed(SendNativeReadingReminderPushBatch::class, 1);
});

it('recovers a pending delivery after queue dispatch fails', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:00:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    ReadingLog::factory()->for($device['user'])->create(['date_read' => '2026-05-25']);
    $originalDispatcher = $this->app->make(Dispatcher::class);
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->andThrow(new RuntimeException('Queue unavailable.'));
    $this->app->instance(Dispatcher::class, $dispatcher);

    expect(fn () => $this->artisan('push:dispatch-native-reading-reminders'))
        ->toThrow(RuntimeException::class, 'Queue unavailable.');

    $delivery = NativePushReminderDelivery::query()->sole();
    expect($delivery->sent_at)->toBeNull()
        ->and($delivery->failed_at)->toBeNull()
        ->and($delivery->updated_at->equalTo(now()))->toBeTrue();

    $this->app->instance(Dispatcher::class, $originalDispatcher);
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:15:00', 'America/Toronto'));

    $this->artisan('push:dispatch-native-reading-reminders')
        ->expectsOutput('Native reading reminder pushes queued: 1 due, 0 skipped.')
        ->assertSuccessful();

    expect($delivery->fresh()->updated_at->equalTo(now()))->toBeTrue();
    Queue::assertPushed(SendNativeReadingReminderPushBatch::class, 1);

    $this->artisan('push:dispatch-native-reading-reminders')
        ->expectsOutput('Native reading reminder pushes queued: 0 due, 1 skipped.')
        ->assertSuccessful();

    Queue::assertPushed(SendNativeReadingReminderPushBatch::class, 1);
});

it('uses per-delivery overlap locks across batches with shared deliveries', function (): void {
    $firstBatchLocks = (new SendNativeReadingReminderPushBatch([3, 8]))->middleware();
    $secondBatchLocks = (new SendNativeReadingReminderPushBatch([8, 12]))->middleware();
    $firstKeys = array_map(fn ($lock): string => $lock->key, $firstBatchLocks);
    $secondKeys = array_map(fn ($lock): string => $lock->key, $secondBatchLocks);

    expect($firstKeys)->toBe([
        'native-reading-reminder-delivery-3',
        'native-reading-reminder-delivery-8',
    ])
        ->and($secondKeys)->toBe([
            'native-reading-reminder-delivery-8',
            'native-reading-reminder-delivery-12',
        ]);
});

it('uses native opt-in independently of web reminder preferences', function (): void {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-05-25']);
    nativeReminderDevice($user);

    $this->artisan('push:dispatch-native-reading-reminders')
        ->expectsOutput('Native reading reminder pushes queued: 1 due, 0 skipped.')
        ->assertSuccessful();

    expect(NativePushReminderDelivery::query()->count())->toBe(1);
    Queue::assertPushed(SendNativeReadingReminderPushBatch::class, 1);
});

it('rechecks reading state and sends a native reminder only while it is still due', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_type' => 'daily_reading',
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
        'token_hash' => $device['registration']->token_hash,
    ]);
    Http::fake(['https://exp.host/--/api/v2/push/send' => Http::response([
        'data' => [['status' => 'ok', 'id' => 'ticket-1']],
    ])]);

    (new SendNativeReadingReminderPushBatch([$delivery->id]))->handle(
        app(ExpoPushService::class),
        app(ReadingReminderConditionService::class),
    );

    expect($delivery->fresh()->sent_at)->not->toBeNull()
        ->and($delivery->fresh()->expo_ticket_id)->toBe('ticket-1');
    Http::assertSentCount(1);

    $secondDevice = nativeReminderDevice($device['user'], 'ExpoPushToken[second-device]');
    $suppressed = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $secondDevice['registration']->id,
        'reminder_type' => 'daily_reading',
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
        'token_hash' => $secondDevice['registration']->token_hash,
    ]);
    ReadingLog::factory()->for($device['user'])->create(['date_read' => '2026-05-26']);

    (new SendNativeReadingReminderPushBatch([$suppressed->id]))->handle(
        app(ExpoPushService::class),
        app(ReadingReminderConditionService::class),
    );

    expect($suppressed->fresh()->skipped_at)->not->toBeNull();
    Http::assertSentCount(1);
});

it('stores redacted Expo ticket diagnostics for a failed delivery', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $pushToken = 'ExpoPushToken[device-secret]';
    $device = nativeReminderDevice(address: $pushToken);
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_type' => 'daily_reading',
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinute(),
        'token_hash' => $device['registration']->token_hash,
    ]);
    $otherDevice = nativeReminderDevice();
    $otherDelivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $otherDevice['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
        'token_hash' => $otherDevice['registration']->token_hash,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [[
                'status' => 'error',
                'message' => "Expo could not send to {$pushToken}.",
                'details' => ['error' => 'InvalidCredentials'],
            ]],
        ]),
    ]);

    (new SendNativeReadingReminderPushBatch([$delivery->id]))->handle(
        app(ExpoPushService::class),
        app(ReadingReminderConditionService::class),
    );

    expect($delivery->fresh()->expo_ticket_error_code)->toBe('InvalidCredentials')
        ->and($delivery->fresh()->expo_ticket_error_message)->toBe('Expo could not send to [redacted Expo push token].')
        ->and($delivery->fresh()->failed_at)->not->toBeNull()
        ->and($otherDelivery->fresh()->sent_at)->toBeNull()
        ->and($otherDelivery->fresh()->failed_at)->toBeNull()
        ->and(NativePushRegistration::query()->find($device['registration']->id))->not->toBeNull();
    Http::assertSent(function ($request) use ($pushToken): bool {
        return $request->url() === 'https://exp.host/--/api/v2/push/send'
            && count($request->data()) === 1
            && $request->data()[0]['to'] === $pushToken;
    });
});

it('marks a delivery failed after Expo requests fail without a ticket response', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinute(),
        'token_hash' => $device['registration']->token_hash,
    ]);
    (new SendNativeReadingReminderPushBatch([$delivery->id]))
        ->failed(new RuntimeException('Expo transport failure.'));

    expect($delivery->fresh()->failed_at)->not->toBeNull()
        ->and($delivery->fresh()->failure_reason)->toBe('Expo push service request failed.')
        ->and($delivery->fresh()->expo_ticket_error_code)->toBeNull()
        ->and($delivery->fresh()->expo_ticket_error_message)->toBeNull();
});

it('removes a registration when Expo immediately rejects its token as unregistered', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $device = nativeReminderDevice(address: 'ExpoPushToken[invalid]');
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
        'token_hash' => $device['registration']->token_hash,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [[
                'status' => 'error',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]],
        ]),
    ]);

    (new SendNativeReadingReminderPushBatch([$delivery->id]))->handle(
        app(ExpoPushService::class),
        app(ReadingReminderConditionService::class),
    );

    expect(NativePushRegistration::query()->find($device['registration']->id))->toBeNull()
        ->and(NativePushReminderDelivery::query()->find($delivery->id))->toBeNull();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://exp.host/--/api/v2/push/send'
        && $request->data()[0]['to'] === 'ExpoPushToken[invalid]');
});

it('preserves a rotated registration when an old ticket reports DeviceNotRegistered', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $device = nativeReminderDevice(address: 'ExpoPushToken[old]');
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
        'token_hash' => $device['registration']->token_hash,
    ]);
    $rotatedToken = 'ExpoPushToken[new]';
    $rotatedTokenHash = hash('sha256', $rotatedToken);
    Http::preventStrayRequests();
    Http::fake(function ($request) use ($device, $rotatedToken, $rotatedTokenHash) {
        $device['registration']->forceFill([
            'expo_push_token' => $rotatedToken,
            'token_hash' => $rotatedTokenHash,
        ])->save();

        return Http::response([
            'data' => [[
                'status' => 'error',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]],
        ]);
    });

    (new SendNativeReadingReminderPushBatch([$delivery->id]))->handle(
        app(ExpoPushService::class),
        app(ReadingReminderConditionService::class),
    );

    expect($device['registration']->fresh()->token_hash)->toBe($rotatedTokenHash)
        ->and($delivery->fresh()->failed_at)->not->toBeNull();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://exp.host/--/api/v2/push/send'
        && $request->data()[0]['to'] === 'ExpoPushToken[old]');
});

it('removes only the matching registration after a DeviceNotRegistered receipt', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinutes(20),
        'token_hash' => $device['registration']->token_hash,
        'expo_ticket_id' => 'ticket-1',
        'expo_receipt_status' => NativePushReceiptStatus::Pending,
        'sent_at' => now()->subMinutes(20),
    ]);
    Http::fake(['https://exp.host/--/api/v2/push/getReceipts' => Http::response([
        'data' => ['ticket-1' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]],
    ])]);

    $this->artisan('push:process-native-reading-reminder-receipts')->assertSuccessful();

    expect(NativePushRegistration::query()->find($device['registration']->id))->toBeNull()
        ->and(NativePushReminderDelivery::query()->find($delivery->id))->toBeNull();
});

it('does not remove a newly rotated address when an old receipt reports DeviceNotRegistered', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $oldHash = $device['registration']->token_hash;
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinutes(20),
        'token_hash' => $oldHash,
        'expo_ticket_id' => 'ticket-1',
        'expo_receipt_status' => NativePushReceiptStatus::Pending,
        'sent_at' => now()->subMinutes(20),
    ]);
    $device['registration']->forceFill([
        'expo_push_token' => 'ExpoPushToken[rotated]',
        'token_hash' => hash('sha256', 'ExpoPushToken[rotated]'),
    ])->save();
    Http::fake(['https://exp.host/--/api/v2/push/getReceipts' => Http::response([
        'data' => ['ticket-1' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]],
    ])]);

    $this->artisan('push:process-native-reading-reminder-receipts')->assertSuccessful();

    expect(NativePushRegistration::query()->find($device['registration']->id))->not->toBeNull()
        ->and($delivery->fresh()->expo_receipt_status)->toBe(NativePushReceiptStatus::Error);
});

it('stops polling for a receipt after Expo retention expires', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00', 'America/Toronto'));
    $expiredDevice = nativeReminderDevice();
    $expiredDelivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $expiredDevice['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subHours(25),
        'token_hash' => $expiredDevice['registration']->token_hash,
        'expo_ticket_id' => 'ticket-expired',
        'expo_receipt_status' => NativePushReceiptStatus::Pending,
        'sent_at' => now()->subHours(24)->subMinute(),
    ]);
    $pendingDevice = nativeReminderDevice();
    $pendingDelivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $pendingDevice['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinutes(20),
        'token_hash' => $pendingDevice['registration']->token_hash,
        'expo_ticket_id' => 'ticket-pending',
        'expo_receipt_status' => NativePushReceiptStatus::Pending,
        'sent_at' => now()->subMinutes(20),
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/getReceipts' => Http::response(['data' => []]),
    ]);

    $this->artisan('push:process-native-reading-reminder-receipts')
        ->expectsOutput('Native reading reminder receipts processed: 0; unavailable after retention: 1; rate-limited retries queued: 0.')
        ->assertSuccessful();

    expect($expiredDelivery->fresh()->expo_receipt_status)->toBe(NativePushReceiptStatus::Unavailable)
        ->and($expiredDelivery->fresh()->expo_receipt_error)->toBe('Expo receipt unavailable after the 24-hour retention period.')
        ->and($expiredDelivery->fresh()->expo_receipt_checked_at)->not->toBeNull()
        ->and($pendingDelivery->fresh()->expo_receipt_status)->toBe(NativePushReceiptStatus::Pending);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://exp.host/--/api/v2/push/getReceipts'
        && $request->data()['ids'] === ['ticket-pending']);
});

it('schedules MessageRateExceeded receipts with exponential backoff', function (int $retryCount, int $delaySeconds): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinutes(20),
        'token_hash' => $device['registration']->token_hash,
        'expo_ticket_id' => 'ticket-rate-limited',
        'expo_receipt_status' => NativePushReceiptStatus::Pending,
        'expo_retry_count' => $retryCount,
        'sent_at' => now()->subMinutes(20),
    ]);
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/getReceipts' => Http::response([
            'data' => [
                'ticket-rate-limited' => [
                    'status' => 'error',
                    'details' => ['error' => 'MessageRateExceeded'],
                ],
            ],
        ]),
    ]);

    $this->artisan('push:process-native-reading-reminder-receipts')
        ->expectsOutput('Native reading reminder receipts processed: 1; unavailable after retention: 0; rate-limited retries queued: 0.')
        ->assertSuccessful();

    expect($delivery->fresh()->expo_receipt_status)->toBe(NativePushReceiptStatus::RetryPending)
        ->and($delivery->fresh()->expo_receipt_error)->toBe('MessageRateExceeded')
        ->and($delivery->fresh()->expo_retry_count)->toBe($retryCount + 1)
        ->and($delivery->fresh()->sent_at)->toBeNull()
        ->and($delivery->fresh()->expo_retry_at->timestamp - now()->timestamp)->toBe($delaySeconds);
    Queue::assertNothingPushed();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://exp.host/--/api/v2/push/getReceipts'
        && $request->data()['ids'] === ['ticket-rate-limited']);
})->with([
    'first retry' => [0, 900],
    'second retry' => [1, 1800],
    'third retry' => [2, 3600],
]);

it('stops retrying a delivery after three rate-limit retries', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinutes(20),
        'token_hash' => $device['registration']->token_hash,
        'expo_ticket_id' => 'ticket-rate-limited',
        'expo_receipt_status' => NativePushReceiptStatus::Pending,
        'expo_retry_count' => 3,
        'sent_at' => now()->subMinutes(20),
    ]);
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/getReceipts' => Http::response([
            'data' => [
                'ticket-rate-limited' => [
                    'status' => 'error',
                    'details' => ['error' => 'MessageRateExceeded'],
                ],
            ],
        ]),
    ]);

    $this->artisan('push:process-native-reading-reminder-receipts')
        ->expectsOutput('Native reading reminder receipts processed: 1; unavailable after retention: 0; rate-limited retries queued: 0.')
        ->assertSuccessful();

    expect($delivery->fresh()->expo_receipt_status)->toBe(NativePushReceiptStatus::Error)
        ->and($delivery->fresh()->expo_receipt_error)->toBe('MessageRateExceeded')
        ->and($delivery->fresh()->expo_retry_count)->toBe(3)
        ->and($delivery->fresh()->expo_retry_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('sends a due rate-limit retry through the existing batch job', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00', 'America/Toronto'));
    config(['queue.default' => 'sync']);
    $device = nativeReminderDevice();
    ReadingLog::factory()->for($device['user'])->create(['date_read' => '2026-05-25']);
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinutes(20),
        'token_hash' => $device['registration']->token_hash,
        'expo_ticket_id' => 'ticket-rate-limited',
        'expo_receipt_status' => NativePushReceiptStatus::RetryPending,
        'expo_receipt_error' => 'MessageRateExceeded',
        'expo_receipt_checked_at' => now()->subMinutes(5),
        'expo_retry_count' => 1,
        'expo_retry_at' => now()->subSecond(),
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [['status' => 'ok', 'id' => 'ticket-retry-2']],
        ]),
    ]);

    $this->artisan('push:process-native-reading-reminder-receipts')
        ->expectsOutput('Native reading reminder receipts processed: 0; unavailable after retention: 0; rate-limited retries queued: 1.')
        ->assertSuccessful();

    expect($delivery->fresh()->expo_receipt_status)->toBe(NativePushReceiptStatus::Pending)
        ->and($delivery->fresh()->expo_ticket_id)->toBe('ticket-retry-2')
        ->and($delivery->fresh()->expo_retry_count)->toBe(1)
        ->and($delivery->fresh()->expo_retry_at)->toBeNull()
        ->and($delivery->fresh()->expo_receipt_error)->toBeNull()
        ->and($delivery->fresh()->sent_at)->not->toBeNull();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://exp.host/--/api/v2/push/send'
        && $request->data()[0]['to'] === $device['registration']->expo_push_token);
});

it('skips a queued delivery when the address was rotated before sending', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $oldHash = $device['registration']->token_hash;
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
        'token_hash' => $oldHash,
    ]);
    $device['registration']->forceFill([
        'expo_push_token' => 'ExpoPushToken[rotated]',
        'token_hash' => hash('sha256', 'ExpoPushToken[rotated]'),
    ])->save();
    Http::fake();

    (new SendNativeReadingReminderPushBatch([$delivery->id]))->handle(
        app(ExpoPushService::class),
        app(ReadingReminderConditionService::class),
    );

    expect($delivery->fresh()->skipped_at)->not->toBeNull();
    Http::assertNothingSent();
});

it('requeues an unsent delivery when the registration rotates to a new address', function (): void {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $oldHash = $device['registration']->token_hash;
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now()->subMinute(),
        'token_hash' => $oldHash,
    ]);
    $newHash = hash('sha256', 'ExpoPushToken[rotated]');
    $device['registration']->forceFill([
        'expo_push_token' => 'ExpoPushToken[rotated]',
        'token_hash' => $newHash,
    ])->save();

    $this->artisan('push:dispatch-native-reading-reminders')
        ->expectsOutput('Native reading reminder pushes queued: 1 due, 0 skipped.')
        ->assertSuccessful();

    expect($delivery->fresh()->token_hash)->toBe($newHash);
    Queue::assertPushed(SendNativeReadingReminderPushBatch::class, 1);
});

it('skips a queued delivery when the native preference was disabled before sending', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-26 09:05:00', 'America/Toronto'));
    $device = nativeReminderDevice();
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'reminder_date' => '2026-05-26',
        'scheduled_for_at' => now(),
        'token_hash' => $device['registration']->token_hash,
    ]);
    $device['preference']->update(['enabled' => false]);
    Http::fake();

    (new SendNativeReadingReminderPushBatch([$delivery->id]))->handle(
        app(ExpoPushService::class),
        app(ReadingReminderConditionService::class),
    );

    expect($delivery->fresh()->skipped_at)->not->toBeNull();
    Http::assertNothingSent();
});

it('cascades native delivery history when the mobile session logs out', function (): void {
    $device = nativeReminderDevice();
    $delivery = NativePushReminderDelivery::factory()->create([
        'native_push_registration_id' => $device['registration']->id,
        'token_hash' => $device['registration']->token_hash,
    ]);

    $this->withToken($device['plainTextToken'])
        ->deleteJson('/api/v1/auth/token');

    expect(NativePushReminderDelivery::query()->find($delivery->id))->toBeNull();
});

/**
 * @return array{user: User, registration: NativePushRegistration, preference: NativeReminderPreference, plainTextToken: string}
 */
function nativeReminderDevice(?User $user = null, ?string $address = null): array
{
    $user ??= User::factory()->create(['reading_timezone' => 'America/Toronto']);
    $session = $user->createToken('Android', ['mobile']);
    $address ??= 'ExpoPushToken['.fake()->uuid().']';
    $registration = NativePushRegistration::factory()->create([
        'personal_access_token_id' => $session->accessToken->id,
        'expo_push_token' => $address,
    ]);
    $preference = NativeReminderPreference::factory()->for($user)->create([
        'personal_access_token_id' => $session->accessToken->id,
        'enabled' => true,
    ]);

    return compact('user', 'registration', 'preference') + ['plainTextToken' => $session->plainTextToken];
}
