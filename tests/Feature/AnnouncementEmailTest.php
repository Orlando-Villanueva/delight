<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Services\AnnouncementEmailContentRenderer;
use Dom\HTMLDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

it('renders announcement content and a public update link without optional imagery', function () {
    $announcement = Announcement::factory()->create([
        'title' => 'A focused new update',
        'slug' => 'focused-new-update',
        'content' => "## What changed\n\n**Reading plans** are easier to continue.",
        'hero_image_path' => null,
    ]);
    $user = User::factory()->create(['name' => 'Reader']);
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'recipient_email' => $user->email,
        'message_id' => 'announcement-email-delivery-1@delight.test',
    ]);
    $mail = new AnnouncementEmail($announcement, $user, $delivery);

    $html = $mail->render();

    expect($mail->envelope()->subject)->toBe('A focused new update')
        ->and($html)->toContain('<h2>What changed</h2>')
        ->and($html)->toContain('<strong>Reading plans</strong>')
        ->and($html)->toContain(route('announcements.show', 'focused-new-update'))
        ->and($html)->not->toContain('<img src=""');
});

it('renders optional announcement imagery when present', function () {
    $announcement = Announcement::factory()->create([
        'hero_image_path' => 'images/updates/example.png',
    ]);
    $user = User::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'recipient_email' => $user->email,
        'message_id' => 'announcement-email-delivery-2@delight.test',
    ]);

    $html = (new AnnouncementEmail($announcement, $user, $delivery))->render();

    expect($html)->toContain(asset('images/updates/example.png'));
});

it('uses a stable message id and the existing unsubscribe experience', function () {
    $announcement = Announcement::factory()->create();
    $user = User::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'recipient_email' => $user->email,
        'message_id' => 'announcement-email-delivery-3@delight.test',
    ]);
    $mail = new AnnouncementEmail($announcement, $user, $delivery);

    $headers = $mail->headers();

    expect($headers->messageId)->toBe('announcement-email-delivery-3@delight.test')
        ->and($headers->text['List-Unsubscribe'])->toContain($mail->oneClickUnsubscribeUrl)
        ->and($headers->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click')
        ->and(URL::hasValidSignature(Request::create($mail->unsubscribeUrl, 'GET')))->toBeTrue()
        ->and($mail->render())->toContain(e($mail->unsubscribeUrl));
});

it('resolves announcement body links without changing their authored content', function (string $content, string $destination) {
    URL::forceRootUrl('https://delight.example');
    URL::forceScheme('https');
    $announcement = Announcement::factory()->create([
        'slug' => 'reading-update',
        'content' => $content,
    ]);
    $user = User::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
    ]);

    try {
        $html = (new AnnouncementEmail($announcement, $user, $delivery))->render();

        expect($html)->toContain('href="'.e($destination).'"')
            ->and($announcement->fresh()->content)->toBe($content);
    } finally {
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
})->with([
    'reported achievements link' => ['[Achievements](/achievements)', 'https://delight.example/achievements'],
    'unrelated site path with query and fragment' => ['[Plans](/reading-plans?sort=new&view=all#daily)', 'https://delight.example/reading-plans?sort=new&view=all#daily'],
    'article relative path' => ['[Related](related-update)', 'https://delight.example/updates/related-update'],
    'parent relative path' => ['[Dashboard](../dashboard)', 'https://delight.example/dashboard'],
    'fragment only' => ['[Details](#details)', 'https://delight.example/updates/reading-update#details'],
    'query only' => ['[Language](?lang=fr)', 'https://delight.example/updates/reading-update?lang=fr'],
    'protocol relative external link' => ['[External](//example.org/update)', 'https://example.org/update'],
    'absolute external link' => ['[External](https://example.org/update?x=1&y=2#details)', 'https://example.org/update?x=1&y=2#details'],
    'email link' => ['[Email](mailto:hello@example.org)', 'mailto:hello@example.org'],
    'telephone link' => ['[Call](tel:+15145550123)', 'tel:+15145550123'],
    'reference style link' => ["[Feedback][feedback]\n\n[feedback]: /feedback", 'https://delight.example/feedback'],
    'authored HTML link' => ['<a href="/dashboard?view=all&amp;lang=fr">Dashboard</a>', 'https://delight.example/dashboard?view=all&lang=fr'],
]);

it('preserves unicode and inline markup while resolving links', function () {
    $announcement = Announcement::factory()->create([
        'content' => 'Découvrez **la fidélité**. [Lire](/dashboard "Continuer la lecture")',
    ]);
    $user = User::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
    ]);

    $html = (new AnnouncementEmail($announcement, $user, $delivery))->render();

    expect($html)->toContain('Découvrez <strong>la fidélité</strong>.')
        ->and($html)->toContain('title="Continuer la lecture"');
});

it('renders inline images with absolute sources and responsive dimensions', function (string $content, string $source) {
    $announcement = Announcement::factory()->create(['content' => $content]);
    $user = User::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
    ]);

    $html = (new AnnouncementEmail($announcement, $user, $delivery))->render();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
    $image = $document->querySelector('.message img');

    $expectedWidth = str_contains($content, 'width=') ? '1375px' : 'auto';

    expect($image->getAttribute('src'))->toBe($source)
        ->and($image->getAttribute('alt'))->toBe('Reading history')
        ->and($image->getAttribute('style'))->toBe('max-width: 100%; width: '.$expectedWidth.'; height: auto;')
        ->and($image->hasAttribute('width'))->toBeFalse()
        ->and($image->hasAttribute('height'))->toBeFalse();
})->with([
    'Markdown site image' => ['![Reading history](/images/updates/book-completions-history.png)', fn () => url('/images/updates/book-completions-history.png')],
    'article relative image' => ['![Reading history](../images/updates/book-completions-history.png)', fn () => url('/images/updates/book-completions-history.png')],
    'external image with conflicting dimensions' => ['<img src="https://example.org/history.png" alt="Reading history" width="1375" height="800" style="width: 1375px !important; height: 800px;">', 'https://example.org/history.png'],
]);

it('preserves the link around an inline image', function () {
    $announcement = Announcement::factory()->create([
        'content' => '[![Reading history](/images/updates/book-completions-history.png)](/achievements)',
    ]);
    $user = User::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
    ]);

    $html = (new AnnouncementEmail($announcement, $user, $delivery))->render();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

    expect($document->querySelector('.message a')->getAttribute('href'))->toBe(url('/achievements'))
        ->and($document->querySelector('.message a img'))->not->toBeNull();
});

it('preserves image decoration while replacing conflicting sizing styles', function () {
    $announcement = Announcement::factory()->make([
        'content' => '<img src="/images/logo-64.png" alt="Logo" style="border-radius: 12px; WIDTH: 1375px !important; height: 800px; min-width: 900px; max-height: 40px;">',
    ]);

    $html = (new AnnouncementEmailContentRenderer)->render($announcement);
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

    expect($document->querySelector('img')->getAttribute('style'))
        ->toBe('border-radius: 12px; max-width: 100%; width: 1375px; height: auto;');
});

it('renders a test draft using the shared email content without live unsubscribe actions', function () {
    $draft = Announcement::factory()->draft()->make([
        'slug' => 'draft-review',
        'title' => 'Draft review',
        'content' => '[Dashboard](/dashboard) ![History](/images/updates/book-completions-history.png)',
        'hero_image_path' => 'images/updates/example.png',
    ]);
    $mail = AnnouncementEmail::forTest($draft);

    $html = $mail->render();

    expect($mail->envelope()->subject)->toBe('[TEST] Draft review');
    expect($mail->headers()->text)->toBe([]);
    expect($html)->toContain(route('admin.announcements.preview', 'draft-review'))
        ->toContain(url('/dashboard'))
        ->toContain(asset('images/updates/example.png'))
        ->toContain('max-width: 100%; width: auto; height: auto;')
        ->toContain('Test email for draft review.')
        ->not->toContain('Unsubscribe from these emails')
        ->not->toContain('/marketing/unsubscribe');
});

it('renders existing announcements with malformed image sources without aborting', function () {
    $announcement = Announcement::factory()->make([
        'content' => '<img src="//example.org:invalid/history.png" alt="History &amp; details"> ![Logo](/images/logo-64.png)',
    ]);

    $html = (new AnnouncementEmailContentRenderer)->render($announcement);

    expect($html)->toContain('History &amp; details')
        ->not->toContain('example.org:invalid')
        ->toContain(url('/images/logo-64.png'));
});

it('preserves legacy link text when its destination cannot be resolved', function () {
    $announcement = Announcement::factory()->make([
        'content' => '<a href="//example.org:invalid/x">Broken <strong>destination</strong></a> [Dashboard](/dashboard)',
    ]);

    $html = (new AnnouncementEmailContentRenderer)->render($announcement);
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

    expect($document->querySelector('a')->hasAttribute('href'))->toBeFalse();
    expect($html)->toContain('Broken <strong>destination</strong>')->toContain(url('/dashboard'));
});

it('uses the resolved fallback image instead of alternate candidates in email', function () {
    $content = '<picture><source srcset="/images/alternate.png 2x"><img src="/images/logo-64.png" srcset="/images/large.png 2x, //example.org:invalid/image.png 3x" sizes="100vw" alt="Logo"></picture>';
    $announcement = Announcement::factory()->make(['content' => $content]);

    $html = (new AnnouncementEmailContentRenderer)->render($announcement);

    expect($html)->toContain(url('/images/logo-64.png'))
        ->not->toContain('srcset')->not->toContain('sizes=')->not->toContain('<source');
    expect($announcement->content)->toBe($content);
});

it('preserves authored image widths while constraining them to the email container', function (string $attributes, string $width) {
    $announcement = Announcement::factory()->make([
        'content' => '<img src="/images/updates/book-completions-history.png" '.$attributes.'>',
    ]);

    $html = (new AnnouncementEmailContentRenderer)->render($announcement);
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

    expect($document->querySelector('img')->getAttribute('style'))
        ->toBe('max-width: 100%; width: '.$width.'; height: auto;');
})->with([
    'small attribute' => ['width="64" height="64"', '64px'],
    'small inline width' => ['style="width: 64px !important; height: 64px"', '64px'],
    'oversized width' => ['width="1375"', '1375px'],
    'percentage width' => ['style="width: 50%; min-width: 900px"', '50%'],
    'inline width overrides attribute' => ['width="1375" style="width: 64px"', '64px'],
]);
