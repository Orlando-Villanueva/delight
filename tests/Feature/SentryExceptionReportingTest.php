<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\Serializer\PayloadSerializer;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('captures one sanitized exception and preserves the 500 response', function (bool $json, bool $unavailable): void {
    config(['app.debug' => false, 'sentry.environment' => 'testing', 'sentry.release' => 'delight@test-release']);
    $transport = sentryReportingTransport($unavailable);
    $builder = app(ClientBuilder::class);
    $private = 'reader-secret@example.com';
    Route::post('/sentry-test-failure', function () use ($private): never {
        throw new RuntimeException($private, previous: new LogicException('Private reflection'));
    });

    $response = $json
        ? $this->postJson('/sentry-test-failure', ['notes' => $private])
        : $this->post('/sentry-test-failure', ['notes' => $private]);

    $response->assertInternalServerError();
    if ($json) {
        $response->assertExactJson(['message' => 'Server Error']);
    } else {
        $response->assertSee('Server Error')->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
    expect($transport->events)->toHaveCount(1);
    $payload = (new PayloadSerializer($builder->getOptions()))->serialize($transport->events[0]);
    expect($payload)->not->toContain($private)->not->toContain('Private reflection');
    expect($transport->events[0]->getEnvironment())->toBe('testing');
    expect($transport->events[0]->getRelease())->toBe('delight@test-release');
    expect($transport->events[0]->getExceptions())->toHaveCount(2);
    expect($transport->events[0]->getExceptions()[0]->getStacktrace())->not->toBeNull();
})->with([
    'web' => [false, false],
    'API' => [true, false],
    'web with unavailable transport' => [false, true],
    'API with unavailable transport' => [true, true],
]);

it('preserves expected error responses without sending incidents', function (string $exceptionType, int $status): void {
    $transport = sentryReportingTransport();
    Route::get('/sentry-test-expected-error', function () use ($exceptionType): never {
        throw match ($exceptionType) {
            'validation' => ValidationException::withMessages(['notes' => 'The notes field is required.']),
            'authentication' => new AuthenticationException,
            'authorization' => new AuthorizationException,
            'missing' => new NotFoundHttpException,
        };
    });

    $response = $this->getJson('/sentry-test-expected-error');

    $response->assertStatus($status);
    if ($exceptionType === 'validation') {
        $response->assertJsonValidationErrors(['notes' => 'The notes field is required.']);
    }
    expect($transport->events)->toBeEmpty();
})->with([
    'validation 422' => ['validation', 422],
    'authentication 401' => ['authentication', 401],
    'authorization 403' => ['authorization', 403],
    'missing 404' => ['missing', 404],
]);

it('captures existing explicit reports while the request continues successfully', function (): void {
    $transport = sentryReportingTransport();
    Route::get('/sentry-test-report', function (): string {
        report(new RuntimeException('Caught operational failure'));

        return 'Request completed';
    });

    $response = $this->get('/sentry-test-report');

    $response->assertOk()->assertSee('Request completed');
    expect($transport->events)->toHaveCount(1);
    expect($transport->events[0]->getExceptions()[0]->getMechanism()->isHandled())->toBeTrue();
});

it('keeps default capture disabled when a request throws', function (): void {
    config(['app.debug' => false]);
    $httpClient = Mockery::mock(HttpClientInterface::class);
    $httpClient->shouldNotReceive('sendRequest');
    $client = app(ClientBuilder::class)->setHttpClient($httpClient)->getClient();
    app(HubInterface::class)->bindClient($client);
    Route::get('/sentry-test-disabled', function (): never {
        throw new RuntimeException('Local failure');
    });

    $response = $this->getJson('/sentry-test-disabled');

    $response->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
    expect($client->getOptions()->getDsn())->toBeNull();
});

it('captures each worker failure once and preserves retry and terminal failure state', function (bool $unavailable): void {
    $transport = sentryReportingTransport($unavailable);
    Cache::forget('sentry-test-job-failed');
    Queue::connection('database')->push(new SentryReportingFailingJob);
    $worker = app('queue.worker');
    $options = new WorkerOptions(sleep: 0, maxTries: 2, backoff: 0);

    $worker->runNextJob('database', 'default', $options);

    expect($transport->events)->toHaveCount(1);
    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['attempts' => 1, 'reserved_at' => null]);
    expect(Cache::get('sentry-test-job-failed'))->toBeNull();

    $worker->runNextJob('database', 'default', $options);

    expect($transport->events)->toHaveCount(2);
    $this->assertDatabaseCount('jobs', 0);
    expect(Cache::get('sentry-test-job-failed'))->toBeTrue();
})->with(['available' => false, 'unavailable' => true]);

function sentryReportingTransport(bool $unavailable = false): TransportInterface
{
    config(['sentry.dsn' => 'https://public@example.invalid/1']);
    $transport = new class($unavailable) implements TransportInterface
    {
        /** @var list<Event> */
        public array $events = [];

        public function __construct(private bool $unavailable) {}

        public function send(Event $event): Result
        {
            $this->events[] = $event;

            if ($this->unavailable) {
                throw new RuntimeException('Transport unavailable');
            }

            return new Result(ResultStatus::success(), $event);
        }

        public function close(?int $timeout = null): Result
        {
            return new Result(ResultStatus::success());
        }
    };
    $client = app(ClientBuilder::class)->setTransport($transport)->getClient();
    app(HubInterface::class)->bindClient($client);

    return $transport;
}

class SentryReportingFailingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(): never
    {
        throw new RuntimeException('Test queue failure');
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put('sentry-test-job-failed', true);
    }
}
