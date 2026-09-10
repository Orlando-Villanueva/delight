<?php

use Symfony\Component\Process\Process;

it('detects a browser timezone once and keeps fallback pages usable', function (string $scenario) {
    $source = file_get_contents(__DIR__.'/../../resources/views/partials/reading-timezone-detection.blade.php');
    $source = preg_replace('/<\/?script[^>]*>/', '', $source);
    $process = new Process(['node', '--input-type=module', '-e', <<<'JS'
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import assert from 'node:assert/strict';
const { source, scenario } = JSON.parse(readFileSync(0, 'utf8'));
let cookie = scenario === 'already reported' ? 'reading_timezone_report=Asia%2FTokyo' : '';
let writes = 0;
let navigations = 0;
const document = {
    currentScript: { dataset: { reloadAfterDetection: scenario === 'login page' ? 'false' : 'true' } },
    get cookie() { return cookie; },
    set cookie(value) {
        writes++;
        if (scenario !== 'cookies blocked') cookie = value.split(';')[0];
    },
};
const Intl = {
    DateTimeFormat() {
        if (scenario === 'unsupported') throw new Error('Unsupported');
        return { resolvedOptions: () => ({ timeZone: scenario === 'empty timezone' ? '' : 'Asia/Tokyo' }) };
    },
};
const location = {
    protocol: 'https:', href: 'https://delight.test/logs/create',
    replace(url) { assert.equal(url, this.href); navigations++; },
};
runInNewContext(source, { document, Intl, location });
assert.equal(navigations, scenario === 'first visit' ? 1 : 0);
assert.equal(writes, ['first visit', 'cookies blocked', 'login page'].includes(scenario) ? 1 : 0);
if (scenario === 'first visit') {
    assert.equal(cookie, 'reading_timezone_report=Asia%2FTokyo');
    runInNewContext(source, { document, Intl, location });
    assert.equal(navigations, 1);
}
JS]);
    $process->setInput(json_encode(['source' => $source, 'scenario' => $scenario], JSON_THROW_ON_ERROR));
    $process->mustRun();

    expect($process->isSuccessful())->toBeTrue();
})->with(['first visit', 'already reported', 'unsupported', 'empty timezone', 'cookies blocked', 'login page']);
