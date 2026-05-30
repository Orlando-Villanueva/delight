<?php

it('restores previous reminder device state when current-device unsubscribe fails', function () {
    $javascript = file_get_contents(__DIR__.'/../../resources/js/app.js');

    expect($javascript)->toContain('previousDeviceEnabled')
        ->and($javascript)->toContain('previousAccountHasDevices')
        ->and($javascript)->toContain('setEnabledState(previousDeviceEnabled, previousAccountHasDevices, false)')
        ->and($javascript)->not->toContain('setEnabledState(true, false, false)');
});

it('keeps reminder setup retryable when browser permission is dismissed', function () {
    $javascript = file_get_contents(__DIR__.'/../../resources/js/app.js');

    expect($javascript)->toContain("if (permission === 'denied')")
        ->and($javascript)->toContain('Browser permission was not enabled.')
        ->and($javascript)->toContain('setEnabledState(false, previousAccountHasDevices)')
        ->and($javascript)->not->toContain('if (permission !== \'granted\') {'.PHP_EOL.'                            setEnabledState(false, false);'.PHP_EOL.'                            showBlocked();');
});
