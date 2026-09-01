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
