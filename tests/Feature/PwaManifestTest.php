<?php

test('manifest screenshots reference existing assets with accurate dimensions', function () {
    $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['screenshots'])->toHaveCount(2);
    expect(array_column($manifest['screenshots'], 'src'))->toBe([
        '/images/screenshots/mobile-v3.png',
        '/images/screenshots/desktop-v3.png',
    ]);

    foreach ($manifest['screenshots'] as $screenshot) {
        $path = public_path(ltrim($screenshot['src'], '/'));

        expect($path)->toBeFile();

        [$width, $height] = getimagesize($path);

        expect($screenshot['sizes'])->toBe($width.'x'.$height);
    }
});
