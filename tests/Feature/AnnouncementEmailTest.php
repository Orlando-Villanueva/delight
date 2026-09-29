<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
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
