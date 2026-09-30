<?php

use App\Models\Announcement;
use App\Services\AnnouncementEmailLinkValidator;
use App\Services\AnnouncementService;
use Illuminate\Validation\ValidationException;

it('allows structurally valid links without checking destination existence', function (string $reference) {
    $announcement = Announcement::factory()->draft()->make([
        'content' => '<a href="'.e($reference).'">Review destination</a>',
    ]);

    app(AnnouncementEmailLinkValidator::class)->validate($announcement);

    expect($announcement->is_draft)->toBeTrue();
})->with([
    '/achievements?source=email#history', '/a-path-that-does-not-exist',
    '../dashboard', '#details', '?lang=fr', '//example.org/update',
    'https://achievements', 'https://example.org/missing',
    'mailto:hello@example.org?subject=Hello', 'tel:+15145550123',
]);

it('reports the authored label and destination for malformed or unsupported links', function (string $reference, string $problem) {
    $announcement = Announcement::factory()->draft()->make([
        'content' => '<a href="'.e($reference).'">Review destination</a>',
    ]);

    try {
        app(AnnouncementEmailLinkValidator::class)->validate($announcement);
        $this->fail('Expected link validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['content'])->toBe([
            'Link "Review destination" ('.$reference.'): '.$problem,
        ]);
    }
})->with([
    'empty' => ['', 'Malformed URL.'],
    'host missing' => ['https://', 'Malformed URL.'],
    'space in host' => ['https://broken host.example', 'Malformed URL.'],
    'invalid port' => ['https://example.org:invalid/path', 'Malformed URL.'],
    'unsafe scheme' => ['javascript:alert(1)', 'Unsupported scheme. Use http, https, mailto, or tel.'],
    'unsupported scheme' => ['ftp://example.org/update', 'Unsupported scheme. Use http, https, mailto, or tel.'],
    'empty mail recipient' => ['mailto:', 'Malformed email address.'],
    'empty phone number' => ['tel:', 'Malformed telephone number.'],
]);

it('inspects markdown references and linked images and collects all errors', function () {
    $announcement = Announcement::factory()->draft()->make([
        'content' => "[Broken][target]\n\n[target]: https://\n\n[![History](/images/history.png)](ftp://example.org/history)",
    ]);

    try {
        app(AnnouncementEmailLinkValidator::class)->validate($announcement);
        $this->fail('Expected link validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['content'])->toHaveCount(2);
    }
});

it('rejects invalid links in direct publication without creating a record', function () {
    $attributes = Announcement::factory()->draft()->make(['content' => '[Broken](https://)'])->getAttributes();

    expect(fn () => app(AnnouncementService::class)->createPublishedOrScheduled($attributes))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('announcements', 0);
});

it('allows an invalid link to be saved as a draft but prevents publication', function () {
    $attributes = Announcement::factory()->draft()->make(['content' => '[Broken](https://)'])->getAttributes();
    $draft = app(AnnouncementService::class)->createDraft($attributes);

    expect(fn () => app(AnnouncementService::class)->publishDraft($draft, now()))
        ->toThrow(ValidationException::class);

    expect($draft->fresh()->is_draft)->toBeTrue();
    expect($draft->fresh()->email_broadcast_authorized_at)->toBeNull();
});

it('rejects malformed image sources before publication', function (string $content) {
    $draft = Announcement::factory()->draft()->create(['content' => $content]);

    try {
        app(AnnouncementService::class)->publishDraft($draft, now());
        $this->fail('Expected image validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['content'])->toBe([
            'Image "History" (//example.org:invalid/history.png): Malformed URL.',
        ]);
    }

    expect($draft->fresh()->is_draft)->toBeTrue();
    expect($draft->fresh()->email_broadcast_authorized_at)->toBeNull();
})->with([
    'Markdown' => '![History](//example.org:invalid/history.png)',
    'HTML' => '<img src="//example.org:invalid/history.png" alt="History">',
]);

it('accepts multiple mailto recipients with encoded addresses and query parameters', function () {
    $announcement = Announcement::factory()->make([
        'content' => '[Email](mailto:one%40example.org,two@example.org?subject=Hello)',
    ]);

    app(AnnouncementEmailLinkValidator::class)->validate($announcement);

    expect($announcement->content)->toContain('one%40example.org,two@example.org');
});

it('rejects a malformed member of a mailto recipient list', function () {
    $announcement = Announcement::factory()->make([
        'content' => '[Email](mailto:one@example.org,invalid)',
    ]);

    expect(fn () => app(AnnouncementEmailLinkValidator::class)->validate($announcement))
        ->toThrow(ValidationException::class);
});
