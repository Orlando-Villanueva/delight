<?php

use Symfony\Component\Process\Process;

it('preserves reading drafts and coordinates submissions across island navigation', function () {
    $process = new Process(['node', '--test', __DIR__.'/ReadingLogIsland.test.js']);
    $process->mustRun();

    expect($process->getOutput())->toContain('# fail 0');
});
