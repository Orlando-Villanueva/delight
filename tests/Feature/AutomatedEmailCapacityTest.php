<?php

use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Services\AutomatedEmailCapacityService;
use App\Services\EmailService;
use App\Services\OnboardingReminderProcessor;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\ClientRequestWatcher;

beforeEach(function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
        'services.brevo.capacity_api_key' => 'private-test-key',
    ]);
    Http::preventStrayRequests();
});

function fakeBrevoCapacity(int $credits = 298): void
{
    Http::fake(['api.brevo.com/v3/account' => Http::response([
        'plan' => [['type' => 'sms', 'creditsType' => 'sendLimit', 'credits' => 1000],
            ['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => $credits]],
    ])]);
}

it('enforces a durable shared allowance and restores slots only after 24 hours', function () {
    $this->freezeSecond();
    fakeBrevoCapacity();
    DB::table('automated_email_reservations')->insert(array_fill(0, 199, ['purpose' => 'announcement', 'reserved_at' => now()->utc()]));

    expect(app(AutomatedEmailCapacityService::class)->reserve('onboarding-reminder'))->toBeNull();
    expect((new AutomatedEmailCapacityService)->reserve('reading-log-reengagement-1'))->toContain('200-email');
    $this->travel(23)->hours();
    expect((new AutomatedEmailCapacityService)->reserve('announcement'))->toContain('200-email');
    $this->travel(1)->hours();
    expect((new AutomatedEmailCapacityService)->reserve('announcement'))->toBeNull();
    $this->assertDatabaseCount('automated_email_reservations', 201);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.brevo.com/v3/account'
        && $request->hasHeader('api-key', 'private-test-key'));
});

it('preserves provider headroom without consuming a local slot', function (int $credits) {
    fakeBrevoCapacity($credits);

    expect(app(AutomatedEmailCapacityService::class)->reserve('announcement'))->toContain('headroom');
    $this->assertDatabaseCount('automated_email_reservations', 0);
})->with([0, 100]);

it('fails closed on missing credentials without calling the provider', function () {
    config(['services.brevo.capacity_api_key' => null]);

    expect(app(AutomatedEmailCapacityService::class)->reserve('announcement'))->toContain('not configured');
    Http::assertNothingSent();
    $this->assertDatabaseCount('automated_email_reservations', 0);
});

it('fails closed on invalid or ambiguous provider data', function (array $payload) {
    Http::fake(['api.brevo.com/v3/account' => Http::response($payload)]);

    expect(app(AutomatedEmailCapacityService::class)->reserve('announcement'))->not->toBeNull();
    $this->assertDatabaseCount('automated_email_reservations', 0);
})->with([
    'missing' => [[]],
    'negative' => [['plan' => [['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => -1]]]],
    'fractional' => [['plan' => [['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => 200.5]]]],
    'boolean' => [['plan' => [['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => true]]]],
    'ambiguous' => [['plan' => array_fill(0, 2, ['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => 298])]],
]);

it('defers safely on provider failures', function (int $status) {
    Http::fake(['api.brevo.com/v3/account' => Http::response(['message' => 'private-response'], $status)]);

    $reason = app(AutomatedEmailCapacityService::class)->reserve('announcement');

    expect($reason)->not->toBeNull()->not->toContain('private');
    $this->assertDatabaseCount('automated_email_reservations', 0);
})->with([302, 401, 403, 429, 500]);

it('defers safely on connection failure', function () {
    Http::fake(['api.brevo.com/v3/account' => Http::failedConnection()]);

    expect(app(AutomatedEmailCapacityService::class)->reserve('announcement'))->toContain('unavailable');
    $this->assertDatabaseCount('automated_email_reservations', 0);
});

it('defers announcement recipients without consuming attempts and resumes without duplicates', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-11-08 12:00:01'));
    $user = User::factory()->create(['created_at' => now()->subDay()]);
    $announcement = Announcement::factory()->create(['starts_at' => now(), 'email_broadcast_authorized_at' => now()]);
    Http::fake(['api.brevo.com/v3/account' => Http::sequence()
        ->push(['plan' => [['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => 100]]])
        ->push(['plan' => [['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => 298]]])]);

    $this->artisan('announcements:send-published-emails')->expectsOutputToContain('1 recipients deferred for capacity')->assertSuccessful();
    $delivery = AnnouncementEmailDelivery::query()->sole();
    expect($delivery->attempt_count)->toBe(0)
        ->and($delivery->failed_at)->toBeNull()
        ->and($delivery->sending_at)->toBeNull()
        ->and($delivery->next_attempt_at->format('Y-m-d H:i:s'))->toBe('2026-11-08 13:00:00');
    expect($announcement->fresh()->email_broadcast_completed_at)->toBeNull();
    Mail::assertNothingSent();

    $this->travelTo(Carbon::parse('2026-11-08 13:00:00'));
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($announcement->fresh()->email_broadcast_completed_at)->not->toBeNull();
    Mail::assertSentCount(1);
    $this->assertDatabaseCount('automated_email_reservations', 1);
});

it('keeps reservations after transport failure and leaves account email outside the bulk allowance', function () {
    fakeBrevoCapacity();
    $service = new EmailService;
    expect($service->sendWithErrorHandling(fn () => throw new RuntimeException('Rejected'), 'onboarding-reminder'))->toBeFalse();
    $this->assertDatabaseCount('automated_email_reservations', 1);

    config(['services.brevo.capacity_api_key' => null]);
    expect($service->sendWithErrorHandling(fn () => null, 'password-reset'))->toBeTrue();
    $this->assertDatabaseCount('automated_email_reservations', 1);
});

it('keeps idle announcement processing free of provider calls', function () {
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    Http::assertNothingSent();
    $this->assertDatabaseCount('automated_email_reservations', 0);
});

it('preserves pending onboarding markers when the shared allowance is exhausted', function () {
    $this->freezeSecond();
    fakeBrevoCapacity();
    $user = User::factory()->create(['onboarding_reminder_requested_at' => now()->subDay()]);
    $marker = $user->getRawOriginal('onboarding_reminder_requested_at');
    DB::table('automated_email_reservations')->insert(array_fill(0, 200, ['purpose' => 'announcement', 'reserved_at' => now()->utc()]));
    Mail::fake();

    $result = app(OnboardingReminderProcessor::class)->process($user->id);

    expect($result)->toBe('deferred')
        ->and($user->fresh()->getRawOriginal('onboarding_reminder_requested_at'))->toBe($marker);
    Mail::assertNothingSent();
    $this->assertDatabaseCount('automated_email_reservations', 200);
});

it('does not expose account responses or credentials to the HTTP profiler', function () {
    $watcher = new ClientRequestWatcher;
    $watcher->register(app());
    Telescope::startRecording(false);
    Telescope::$entriesQueue = [];
    fakeBrevoCapacity();

    try {
        expect(app(AutomatedEmailCapacityService::class)->reserve('announcement'))->toBeNull();
        expect(Telescope::isRecording())->toBeTrue();
        expect(collect(Telescope::$entriesQueue)->where('type', 'client_request'))->toBeEmpty();
    } finally {
        Telescope::stopRecording();
        Telescope::$entriesQueue = [];
    }
});
