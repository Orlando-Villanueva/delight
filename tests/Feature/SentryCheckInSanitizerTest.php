<?php

use App\Services\SentryCheckInSanitizer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Sentry\CheckIn;
use Sentry\CheckInStatus;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Laravel\Features\ConsoleSchedulingIntegration;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\Serializer\PayloadSerializer;
use Sentry\State\HubInterface;
use Sentry\UserDataBag;
use Tests\Support\RecordingSentryTransport;

it('sends only permitted check-in fields through the configured SDK privacy boundary', function (): void {
    config([
        'services.sentry.announcement_cron_enabled' => true,
        'sentry.dsn' => 'https://public@example.invalid/1',
        'sentry.environment' => 'testing',
        'sentry.release' => 'delight@test-release',
    ]);
    $transport = RecordingSentryTransport::bind();
    $private = 'private-reader@example.com';
    $event = Event::createCheckIn()->setCheckIn(new CheckIn(
        SentryCheckInSanitizer::ANNOUNCEMENT_MONITOR_SLUG, CheckInStatus::ok(),
        str_repeat('a', 32), $private, $private, 2.5,
        new MonitorConfig(MonitorSchedule::crontab('* * * * *')),
    ));
    $event->setTags(['recipient' => $private])->setContext('private', ['note' => $private])
        ->setUser(UserDataBag::createFromArray(['email' => $private]));
    app(HubInterface::class)->configureScope(fn ($scope) => $scope->setTag('recipient', $private));

    app(HubInterface::class)->captureEvent($event);

    expect($transport->events)->toHaveCount(1);
    $payload = (new PayloadSerializer(app(ClientBuilder::class)->getOptions()))->serialize($transport->events[0]);
    $parts = explode("\n", $payload);
    expect(json_decode($parts[0], true))->not->toHaveKey('trace');
    expect(json_decode($parts[2], true))->toBe([
        'check_in_id' => str_repeat('a', 32),
        'monitor_slug' => 'announcement-email-scheduler',
        'status' => 'ok',
        'duration' => 2.5,
        'release' => 'delight@test-release',
        'environment' => 'testing',
    ]);
    expect($payload)->not->toContain($private);
});

it('drops disabled or unapproved monitor check-ins', function (bool $enabled, ?string $dsn, string $slug): void {
    config(['services.sentry.announcement_cron_enabled' => $enabled, 'sentry.dsn' => $dsn]);
    $event = Event::createCheckIn()->setCheckIn(new CheckIn($slug, CheckInStatus::inProgress()));

    expect(SentryCheckInSanitizer::sanitize($event))->toBeNull();
})->with([
    'disabled' => [false, 'https://public@example.invalid/1', 'announcement-email-scheduler'],
    'Sentry disabled' => [true, null, 'announcement-email-scheduler'],
    'other monitor' => [true, 'https://public@example.invalid/1', 'private-monitor'],
]);

it('drops events without a check-in payload', function (bool $checkInType): void {
    config(['services.sentry.announcement_cron_enabled' => true, 'sentry.dsn' => 'https://public@example.invalid/1']);
    $event = $checkInType ? Event::createCheckIn() : Event::createEvent();

    expect(SentryCheckInSanitizer::sanitize($event))->toBeNull();
})->with(['missing payload' => true, 'exception event' => false]);

it('attaches check-ins only to the opted-in announcement schedule and tolerates transport failures', function (bool $enabled, ?string $dsn, int $expected, bool $unavailable): void {
    config(['services.sentry.announcement_cron_enabled' => $enabled, 'sentry.dsn' => $dsn]);
    $transport = RecordingSentryTransport::bind($unavailable, 'Test transport unavailable', false);
    app(ConsoleSchedulingIntegration::class)->onBoot(app(Dispatcher::class));
    $schedule = new Schedule;
    ScheduleFacade::swap($schedule);
    require base_path('routes/console.php');

    foreach ($schedule->events() as $event) {
        $event->callBeforeCallbacks(app());
        $event->exitCode = 0;
        $event->callAfterCallbacks(app());
    }

    expect($transport->events)->toHaveCount($expected);
    if ($expected !== 0) {
        expect($transport->events[0]->getCheckIn()->getMonitorSlug())->toBe('announcement-email-scheduler');
        expect($transport->events[0]->getCheckIn()->getStatus())->toBe(CheckInStatus::inProgress());
        expect($transport->events[1]->getCheckIn()->getStatus())->toBe(CheckInStatus::ok());
        expect($transport->events[0]->getCheckIn()->getId())->toBe($transport->events[1]->getCheckIn()->getId());
        expect($transport->events[0]->getCheckIn()->getMonitorConfig())->toBeNull();
    }
})->with([
    'opted in' => [true, 'https://public@example.invalid/1', 2, false],
    'disabled' => [false, 'https://public@example.invalid/1', 0, false],
    'Sentry disabled' => [true, null, 0, false],
    'transport unavailable' => [true, 'https://public@example.invalid/1', 2, true],
]);
