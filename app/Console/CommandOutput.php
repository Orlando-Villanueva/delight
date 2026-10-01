<?php

namespace App\Console;

use Illuminate\Console\Command;

class CommandOutput
{
    /** @param array<string, mixed> $summary */
    public function renderSummary(Command $command, array $summary): void
    {
        if ($command->option('json')) {
            $command->line(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return;
        }

        $command->table(['Field', 'Value'], collect($summary)
            ->map(function (mixed $value, string $key): array {
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                return [$key, $value ?? 'None'];
            })->values()->all());
    }

    /** @param array<string, mixed> $summary */
    public function confirmAction(Command $command, bool $interactive, array $summary, string $action, string $prompt): bool
    {
        if (! $command->option('json')) {
            $this->renderSummary($command, $summary);
        }

        if ($command->option('yes')) {
            return true;
        }

        if ($command->option('json') || ! $interactive) {
            $this->renderFailure($command, ['confirmation' => ["{$action} requires --yes when running without interactive confirmation."]]);

            return false;
        }

        $confirmed = $command->confirm($prompt);

        if (! $confirmed) {
            $command->info("{$action} cancelled.");
        }

        return $confirmed;
    }

    /** @param array<string, array<int, string>> $errors */
    public function renderFailure(Command $command, array $errors): int
    {
        if ($command->option('json')) {
            $command->line(json_encode(['errors' => $errors], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($errors as $messages) {
                foreach ($messages as $message) {
                    $command->error($message);
                }
            }
        }

        return Command::FAILURE;
    }
}
