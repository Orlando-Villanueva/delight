<?php

use Symfony\Component\Process\Process;

it('coordinates reading submissions and starts new pages with clean drafts', function () {
    $process = new Process(['node', '--test', __DIR__.'/ReadingLogIsland.test.js']);
    expect($process->run())->toBe(0);
});
