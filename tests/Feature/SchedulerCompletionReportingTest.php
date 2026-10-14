<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

it('proves background completion reporting and duplicate capture through isolated child processes', function (string $outcome, string $callback): void {
    $directory = sys_get_temp_dir().'/del352-scheduler-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);

    try {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/scheduler-reporting-proof.php'), 'schedule:run'], base_path(), [
            'DEL352_PROOF_DIRECTORY' => $directory,
            'DEL352_PROOF_OUTCOME' => $outcome,
            'DEL352_PROOF_CALLBACK' => $callback,
        ], timeout: 15);
        $process->run();
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
        $deadline = microtime(true) + 10;
        while (! is_file($directory.'/completed.json') && microtime(true) < $deadline) {
            usleep(10_000);
        }
        expect(is_file($directory.'/completed.json'))->toBeTrue('Background completion did not arrive.');
        $completion = json_decode(file_get_contents($directory.'/completed.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($completion)->toBe([
            'exit_code' => match ($outcome) {
                'success' => 0, 'exit' => 17, 'throw' => 1
            },
            'mutex_exists' => false,
        ]);
        $hooks = file($directory.'/hooks.jsonl', FILE_IGNORE_NEW_LINES);
        expect($hooks)->toBe([$outcome === 'success' ? '"success"' : '"failure"']);
        $records = is_file($directory.'/events.jsonl')
            ? array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($directory.'/events.jsonl', FILE_IGNORE_NEW_LINES))
            : [];
        $expectedTypes = $outcome === 'throw' ? [RuntimeException::class] : [];
        if ($callback === 'report' && $outcome !== 'success') {
            $expectedTypes[] = LogicException::class;
        }
        expect(array_merge(...array_map(fn ($record) => $record['types'], $records)))->toBe($expectedTypes);
        foreach ($records as $record) {
            expect($record['payload'])->not->toContain('private-scheduler-secret@example.com');
            $payload = json_decode(explode("\n", $record['payload'])[2], true, flags: JSON_THROW_ON_ERROR);
            expect($payload['environment'])->toBe('testing');
            expect($payload['release'])->toBe('delight@scheduler-proof');
            expect($payload['exception']['values'][0]['value'])->toBe('[redacted]');
        }
    } finally {
        File::deleteDirectory($directory);
    }
})->with(['success', 'exit', 'throw'])->with(['none', 'report']);

it('proves background check-in linkage and a separate check-in privacy boundary', function (string $outcome, string $mode): void {
    $directory = sys_get_temp_dir().'/del352-check-in-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);

    try {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/scheduler-reporting-proof.php'), 'schedule:run'], base_path(), [
            'DEL352_PROOF_DIRECTORY' => $directory,
            'DEL352_PROOF_OUTCOME' => $outcome,
            'DEL352_PROOF_CALLBACK' => $mode,
        ], timeout: 15);
        $process->run();
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
        $deadline = microtime(true) + 10;
        while (! is_file($directory.'/completed.json') && microtime(true) < $deadline) {
            usleep(10_000);
        }
        expect(is_file($directory.'/completed.json'))->toBeTrue('Background completion did not arrive.');
        $records = array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($directory.'/events.jsonl', FILE_IGNORE_NEW_LINES));
        $checkIns = array_values(array_filter($records, fn ($record) => $record['type'] === 'check_in'));
        $errors = array_values(array_filter($records, fn ($record) => $record['type'] === 'event'));
        expect($checkIns)->toHaveCount(2);
        expect($errors)->toHaveCount($outcome === 'throw' ? 1 : 0);
        $payloads = array_map(fn ($record) => json_decode(explode("\n", $record['payload'])[2], true, flags: JSON_THROW_ON_ERROR), $checkIns);
        expect($payloads[0]['status'])->toBe('in_progress');
        expect($payloads[1]['status'])->toBe($outcome === 'success' ? 'ok' : 'error');
        expect($payloads[0]['check_in_id'])->toBe($payloads[1]['check_in_id']);
        foreach ($checkIns as $index => $record) {
            expect($payloads[$index]['monitor_slug'])->toBe('del352-scheduler-proof');
            expect($payloads[$index]['environment'])->toBe('testing');
            expect($payloads[$index]['release'])->toBe('delight@scheduler-proof');
            expect($payloads[$index])->not->toHaveKey('monitor_config');
            if ($mode === 'monitor_filtered') {
                expect(array_keys($payloads[$index]))->toEqualCanonicalizing([
                    'check_in_id', 'monitor_slug', 'status', 'duration', 'release', 'environment',
                ]);
                expect($record['payload'])->not->toContain('private-check-in-secret@example.com');
                $header = json_decode(explode("\n", $record['payload'])[0], true, flags: JSON_THROW_ON_ERROR);
                expect($header)->not->toHaveKey('trace');
            } else {
                expect($payloads[$index])->toHaveKey('contexts.trace');
                expect($record['payload'])->not->toContain('private-check-in-secret@example.com');
            }
        }
        foreach ($errors as $record) {
            expect($record['types'])->toBe([RuntimeException::class]);
            expect($record['payload'])->not->toContain('private-scheduler-secret@example.com')
                ->not->toContain('private-check-in-secret@example.com');
        }
        $completion = json_decode(file_get_contents($directory.'/completed.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($completion['mutex_exists'])->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
    }
})->with(['success', 'exit', 'throw'])->with(['monitor', 'monitor_filtered']);
