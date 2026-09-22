<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ExpoPushService
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    public function send(array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        if (count($messages) > 100) {
            throw new RuntimeException('Expo push batches may contain at most 100 messages.');
        }

        return $this->post('push_url', $messages);
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @return array<string, array<string, mixed>>
     */
    public function receipts(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        if (count($ticketIds) > 1000) {
            throw new RuntimeException('Expo receipt batches may contain at most 1000 tickets.');
        }

        $receipts = $this->post('receipts_url', ['ids' => array_values($ticketIds)]);
        $indexed = [];

        foreach ($receipts as $ticketId => $receipt) {
            if (is_string($ticketId) && is_array($receipt)) {
                $indexed[$ticketId] = $receipt;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int|string, mixed>
     */
    private function post(string $configKey, array $payload): array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.expo.timeout', 10))
                ->connectTimeout((int) config('services.expo.connect_timeout', 5))
                ->retry(
                    [250, 500, 1000],
                    0,
                    fn (Throwable $exception): bool => $this->shouldRetry($exception),
                )
                ->post((string) config('services.expo.'.$configKey), $payload);

            $response->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('Expo push service request failed.', previous: $exception);
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Expo push service returned an invalid response.');
        }

        return $data;
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException || ! $exception->response) {
            return false;
        }

        return $exception->response->status() === 429 || $exception->response->serverError();
    }
}
