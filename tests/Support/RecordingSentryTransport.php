<?php

namespace Tests\Support;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

final class RecordingSentryTransport implements TransportInterface
{
    /** @var list<Event> */
    public array $events = [];

    public function __construct(
        private bool $unavailable = false,
        private string $unavailableMessage = 'Transport unavailable',
    ) {}

    public static function bind(
        bool $unavailable = false,
        string $unavailableMessage = 'Transport unavailable',
        bool $configureDsn = true,
    ): self {
        if ($configureDsn) {
            config(['sentry.dsn' => 'https://public@example.invalid/1']);
        }
        $transport = new self($unavailable, $unavailableMessage);
        app(HubInterface::class)->bindClient(app(ClientBuilder::class)->setTransport($transport)->getClient());

        return $transport;
    }

    public function send(Event $event): Result
    {
        $this->events[] = $event;

        if ($this->unavailable) {
            throw new \RuntimeException($this->unavailableMessage);
        }

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
