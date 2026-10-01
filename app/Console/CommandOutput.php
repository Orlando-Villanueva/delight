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
