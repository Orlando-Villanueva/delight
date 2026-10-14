<?php

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Sentry\CheckIn;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Laravel\Features\ConsoleSchedulingIntegration;
use Sentry\Serializer\PayloadSerializer;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

define('ARTISAN_BINARY', __FILE__);
require dirname(__DIR__, 2).'/vendor/autoload.php';

$directory = getenv('DEL352_PROOF_DIRECTORY');
if (! is_string($directory) || ! is_dir($directory)) {
    exit(2);
}

foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $directory.'/config.php', 'SENTRY_ENABLED' => 'false', 'SENTRY_DSN' => '', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'MAIL_MAILER' => 'array', 'TELESCOPE_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config([
    'cache.default' => 'file',
    'cache.stores.file.path' => $directory.'/cache',
    'cache.stores.file.lock_path' => $directory.'/cache',
    'logging.default' => 'single',
    'logging.channels.single.path' => $directory.'/laravel.log',
    'sentry.dsn' => 'https://public@example.invalid/1',
    'sentry.environment' => 'testing',
    'sentry.release' => 'delight@scheduler-proof',
]);
Http::preventStrayRequests();
Mail::fake();
Queue::fake();

$transport = new class($directory) implements TransportInterface
{
    public function __construct(private string $directory) {}

    public function send(Event $event): Result
    {
        $record = [
            'type' => (string) $event->getType(),
            'types' => array_map(fn ($exception): string => $exception->getType(), $event->getExceptions()),
            'payload' => (new PayloadSerializer(app(ClientBuilder::class)->getOptions()))->serialize($event),
        ];
        file_put_contents($this->directory.'/events.jsonl', json_encode($record, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
};
$builder = app(ClientBuilder::class)->setTransport($transport);
if (getenv('DEL352_PROOF_CALLBACK') === 'monitor_filtered') {
    $builder->getOptions()->setBeforeSendCheckInCallback(static function (Event $event): ?Event {
        $checkIn = $event->getCheckIn();
        if ($checkIn === null || $checkIn->getMonitorSlug() !== 'del352-scheduler-proof') {
            return null;
        }

        return Event::createCheckIn($event->getId())->setCheckIn(new CheckIn(
            'del352-scheduler-proof', $checkIn->getStatus(), $checkIn->getId(),
            'delight@scheduler-proof', 'testing', $checkIn->getDuration(),
        ));
    });
}
app(HubInterface::class)->bindClient($builder->getClient());
if (str_starts_with((string) getenv('DEL352_PROOF_CALLBACK'), 'monitor')) {
    app(ConsoleSchedulingIntegration::class)->onBoot(app(Dispatcher::class));
    app(HubInterface::class)->configureScope(static function ($scope): void {
        $scope->setContext('trace', [
            'trace_id' => str_repeat('a', 32), 'span_id' => str_repeat('b', 16),
            'private_note' => 'private-check-in-secret@example.com',
        ]);
        $scope->setTag('recipient', 'private-check-in-secret@example.com');
    });
}

Artisan::command('proof:work', function (): int {
    return match (getenv('DEL352_PROOF_OUTCOME')) {
        'success' => 0,
        'exit' => 17,
        'throw' => throw new RuntimeException('private-scheduler-secret@example.com'),
        default => throw new LogicException('Unsupported scheduler proof outcome.'),
    };
});

$schedule = new Schedule;
$app->instance(Schedule::class, $schedule);
$scheduled = $schedule->command('proof:work')->everyMinute()->withoutOverlapping()->runInBackground();
if (str_starts_with((string) getenv('DEL352_PROOF_CALLBACK'), 'monitor')) {
    $scheduled->sentryMonitor('del352-scheduler-proof', updateMonitorConfig: false);
}
$scheduled->onSuccess(function () use ($directory): void {
    file_put_contents($directory.'/hooks.jsonl', "\"success\"\n", FILE_APPEND | LOCK_EX);
});
$scheduled->onFailure(function () use ($directory): void {
    file_put_contents($directory.'/hooks.jsonl', "\"failure\"\n", FILE_APPEND | LOCK_EX);
    if (getenv('DEL352_PROOF_CALLBACK') === 'report') {
        report(new LogicException('Synthetic scheduler completion failure.'));
    }
});
EventFacade::listen(ScheduledBackgroundTaskFinished::class, function ($event) use ($directory): void {
    file_put_contents($directory.'/completed.json', json_encode([
        'exit_code' => $event->task->exitCode,
        'mutex_exists' => $event->task->mutex->exists($event->task),
    ], JSON_THROW_ON_ERROR), LOCK_EX);
});

$input = new ArgvInput;
if (! in_array($input->getFirstArgument(), ['schedule:run', 'schedule:finish', 'proof:work'], true)) {
    exit(2);
}
$status = $kernel->handle($input, new NullOutput);
$kernel->terminate($input, $status);
exit($status);
