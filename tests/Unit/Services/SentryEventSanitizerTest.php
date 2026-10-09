<?php

use App\Services\SentryEventSanitizer;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Stacktrace;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Sentry\UserDataBag;
use Tests\TestCase;

uses(TestCase::class);

it('sends only permitted diagnostics when private values appear throughout the event', function (): void {
    config(['sentry.environment' => 'production', 'sentry.release' => 'delight@abc123']);
    $transport = new class implements TransportInterface
    {
        public ?Event $event = null;

        public function send(Event $event): Result
        {
            $this->event = $event;

            return new Result(ResultStatus::success(), $event);
        }

        public function close(?int $timeout = null): Result
        {
            return new Result(ResultStatus::success());
        }
    };
    config(['sentry.dsn' => 'https://public@example.invalid/1']);
    $builder = app(ClientBuilder::class);
    $client = $builder->setTransport($transport)->getClient();
    $private = 'private-reader-secret@example.com';
    $frame = new Frame('App\\Services\\ReadingLogService::save', 'app/Services/ReadingLogService.php', 42, $private, '/private/'.$private, ['notes' => $private]);
    $frame->setContextLine($private)->setPreContext([$private])->setPostContext([$private]);
    $event = Event::createEvent()
        ->setMessage($private)
        ->setRequest(['url' => 'https://example.com/'.$private, 'data' => ['notes' => $private], 'headers' => ['Authorization' => $private], 'cookies' => $private])
        ->setUser(UserDataBag::createFromArray(['email' => $private, 'ip_address' => '192.0.2.1']))
        ->setExtra(['nested' => ['push_token' => $private]])
        ->setTags(['recipient' => $private])
        ->setContext('private', ['reflection' => $private])
        ->setTransaction($private)
        ->setFingerprint([$private])
        ->setServerName($private)
        ->setLogger($private)
        ->setBreadcrumb([new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_DEFAULT, 'mail', $private, ['body' => $private])])
        ->setExceptions([
            new ExceptionDataBag(new RuntimeException($private), new Stacktrace([$frame]), new ExceptionMechanism('generic', false, ['recipient' => $private])),
            new ExceptionDataBag(new LogicException($private)),
        ]);

    $eventId = $client->captureEvent($event);

    expect($eventId)->toEqual($event->getId());
    expect($transport->event)->not->toBeNull();
    $payload = (new PayloadSerializer($client->getOptions()))->serialize($transport->event);
    expect($payload)->not->toContain($private)->not->toContain('192.0.2.1');
    $data = json_decode(explode("\n", $payload)[2], true, flags: JSON_THROW_ON_ERROR);
    expect($data['environment'])->toBe('production');
    expect($data['release'])->toBe('delight@abc123');
    expect($data['exception']['values'][1]['type'])->toBe(RuntimeException::class);
    expect($data['exception']['values'][0]['type'])->toBe(LogicException::class);
    expect($data['exception']['values'][0]['value'])->toBe('[redacted]');
    expect($data['exception']['values'][1]['mechanism']['handled'])->toBeFalse();
    expect($data['exception']['values'][1]['stacktrace']['frames'][0])->toMatchArray([
        'filename' => 'app/Services/ReadingLogService.php',
        'function' => 'App\\Services\\ReadingLogService::save',
        'lineno' => 42,
        'in_app' => true,
    ]);
    foreach (['request', 'user', 'extra', 'tags', 'contexts', 'transaction', 'fingerprint', 'server_name', 'logger', 'breadcrumbs', 'message'] as $key) {
        expect($data)->not->toHaveKey($key);
    }
});

it('discards message-only events without invoking the transport', function (): void {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldNotReceive('send');
    config(['sentry.dsn' => 'https://public@example.invalid/1']);
    $builder = app(ClientBuilder::class);
    $client = $builder->setTransport($transport)->getClient();

    $eventId = $client->captureMessage('private note');

    expect($eventId)->toBeNull();
    expect(SentryEventSanitizer::sanitize(Event::createTransaction()))->toBeNull();
});

it('returns safely when an error transport is unavailable', function (): void {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andThrow(new RuntimeException('Transport unavailable'));
    config(['sentry.dsn' => 'https://public@example.invalid/1']);
    $builder = app(ClientBuilder::class);
    $client = $builder->setTransport($transport)->getClient();

    $eventId = $client->captureException(new RuntimeException('Application failure'));

    expect($eventId)->toBeNull();
});

it('leaves capture disabled and other telemetry off in the default application configuration', function (): void {
    $httpClient = Mockery::mock(HttpClientInterface::class);
    $httpClient->shouldNotReceive('sendRequest');
    $builder = app(ClientBuilder::class);
    $client = $builder->setHttpClient($httpClient)->getClient();

    $client->captureException(new RuntimeException('Local failure'));

    expect($client->getOptions()->getDsn())->toBeNull();
    expect($client->getOptions()->getEnableLogs())->toBeFalse();
    expect($client->getOptions()->getEnableMetrics())->toBeFalse();
    expect($client->getOptions()->getEnableTracing())->toBeFalse();
    expect($client->getOptions()->getTracesSampleRate())->toBe(0.0);
    expect($client->getOptions()->getProfilesSampleRate())->toBe(0.0);
});
