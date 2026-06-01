<?php

function readingReminderSettingsJavascript(): string
{
    return file_get_contents(__DIR__.'/../../resources/js/app.js');
}

it('restores previous reminder device state when current-device unsubscribe fails', function () {
    $javascript = readingReminderSettingsJavascript();

    expect($javascript)->toContain('previousDeviceEnabled')
        ->and($javascript)->toContain('previousAccountHasDevices')
        ->and($javascript)->toContain('setEnabledState(previousDeviceEnabled, previousAccountHasDevices, false)')
        ->and($javascript)->not->toContain('setEnabledState(true, false, false)');
});

it('keeps reminder setup retryable when browser permission is dismissed', function () {
    $javascript = readingReminderSettingsJavascript();

    expect($javascript)->toContain("if (permission === 'denied')")
        ->and($javascript)->toContain('Browser permission was not enabled.')
        ->and($javascript)->toContain('setEnabledState(false, previousAccountHasDevices)')
        ->and($javascript)->not->toContain('if (permission !== \'granted\') {'.PHP_EOL.'                            setEnabledState(false, false);'.PHP_EOL.'                            showBlocked();');
});

it('gives Brave users actionable guidance when the push service registration fails', function () {
    $javascript = readingReminderSettingsJavascript();

    expect($javascript)->toContain('const isBraveBrowser = async ()')
        ->and($javascript)->toContain('return await navigator.brave.isBrave()')
        ->and($javascript)->toContain('Use Google services for push messaging')
        ->and($javascript)->toContain('Brave could not connect to its push service.')
        ->and($javascript)->toContain('const getPushSubscriptionFailureMessage = async ()')
        ->and($javascript)->toContain('await getPushSubscriptionFailureMessage()');
});
