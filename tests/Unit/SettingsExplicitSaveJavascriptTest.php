<?php

use Symfony\Component\Process\Process;

it('keeps unsaved reminder choices intact when browser subscription status changes', function () {
    $source = file_get_contents(__DIR__.'/../../resources/js/app.js');
    $start = strpos($source, '            const applySubscriptionState =');
    $end = strpos($source, '            const getCurrentPushSubscription', $start);
    $process = new Process(['node', '--input-type=module', '-e', <<<'JS'
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import assert from 'node:assert/strict';
const input = { checked: false };
const states = [];
runInNewContext(readFileSync(0, 'utf8') + `
applySubscriptionState({ device_enabled: true, account_has_devices: true, daily_reading_reminder_enabled: true });
`, {
    reminderSettings: { querySelector: () => input },
    setEnabledState: (...state) => states.push(state),
});
assert.equal(input.checked, false);
assert.deepEqual(states, [[true, true]]);
JS]);
    $process->setInput(substr($source, $start, $end - $start));
    $process->mustRun();

    expect($process->isSuccessful())->toBeTrue();
});
