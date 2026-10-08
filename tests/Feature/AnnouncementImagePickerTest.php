<?php

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementImageService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
    $this->originalPublicPath = public_path();
    $this->imagePublicPath = sys_get_temp_dir().'/announcement-picker-'.uniqid();
    app()->usePublicPath($this->imagePublicPath);
    File::ensureDirectoryExists(public_path('images/updates/hero'));
    File::ensureDirectoryExists(public_path('images/updates/social'));
    File::put(public_path('images/updates/social/unused.png'), 'fixture');
    foreach (['hero.png', 'social.png', 'body.png', 'unused.png'] as $name) {
        File::put(public_path('images/updates/hero/'.$name), 'fixture');
    }
});

afterEach(function () {
    app()->usePublicPath($this->originalPublicPath);
    File::deleteDirectory($this->imagePublicPath);
});

it('marks field and Markdown image references as used across announcement states', function () {
    Announcement::factory()->create([
        'is_draft' => true, 'hero_image_path' => '/images/updates/hero/hero.png',
        'social_image_path' => asset('images/updates/hero/social.png').'?v=2',
        'content' => "![Body][image]\n\n[image]: /images/updates/hero/body.png#detail",
    ]);
    Announcement::factory()->create([
        'is_draft' => false, 'ends_at' => now()->subDay(),
        'hero_image_path' => 'images/updates/hero/unused.png',
    ]);

    expect(app(AnnouncementImageService::class)->images('hero'))->toBe([
        'images/updates/hero/body.png' => true, 'images/updates/hero/hero.png' => true,
        'images/updates/hero/social.png' => true, 'images/updates/hero/unused.png' => true,
    ])->and(app(AnnouncementImageService::class)->images('hero', true))->toBe([]);
});

it('discovers nested images and excludes non-images and external references from local usage', function () {
    File::ensureDirectoryExists(public_path('images/updates/hero/nested'));
    File::put(public_path('images/updates/hero/nested/image space.JPG'), 'fixture');
    File::put(public_path('images/updates/hero/readme.txt'), 'not an image');
    Announcement::factory()->create([
        'hero_image_path' => 'images/updates/hero/hero.png',
        'content' => '![External](https://external.example/images/updates/hero/body.png) ![Local]('.asset('images/updates/hero/nested/image%20space.JPG').')',
    ]);

    expect(app(AnnouncementImageService::class)->images('hero', true))->toBe([
        'images/updates/hero/body.png' => false, 'images/updates/hero/social.png' => false, 'images/updates/hero/unused.png' => false,
    ]);
});

it('returns an empty picker when the image directory is missing', function () {
    File::deleteDirectory(public_path('images/updates/hero'));
    expect(app(AnnouncementImageService::class)->images('hero'))->toBe([]);
});

it('selects an existing image into the chosen field without saving an announcement', function (string $field, string $folder) {
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    Livewire::test(CreateAnnouncement::class)
        ->callAction(TestAction::make('browseImages')->schemaComponent($field), data: ['unused_only' => true, 'image' => 'images/updates/'.$folder.'/unused.png'])
        ->assertHasNoActionErrors()
        ->assertSet('data.'.$field, 'images/updates/'.$folder.'/unused.png');
    $this->assertDatabaseCount('announcements', 0);
})->with([['hero_image_path', 'hero'], ['social_image_path', 'social']]);

it('rejects invented paths and used images when the unused filter is enabled', function (string $path) {
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    Announcement::factory()->create(['hero_image_path' => 'images/updates/hero/hero.png']);
    Livewire::test(CreateAnnouncement::class)
        ->callAction(TestAction::make('browseImages')->schemaComponent('hero_image_path'), data: ['unused_only' => true, 'image' => $path])
        ->assertHasActionErrors(['image'])
        ->assertSet('data.hero_image_path', null);
})->with(['images/updates/hero/missing.png', 'images/updates/hero/hero.png']);

it('allows deliberate reuse with the filter off and persists the selected image on save', function () {
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    Announcement::factory()->create(['hero_image_path' => 'images/updates/hero/hero.png']);
    Livewire::test(CreateAnnouncement::class)
        ->callAction(TestAction::make('browseImages')->schemaComponent('hero_image_path'), data: ['unused_only' => false, 'image' => 'images/updates/hero/hero.png'])
        ->assertHasNoActionErrors()
        ->fillForm(['title' => 'Selected image draft', 'content' => 'Draft body'])
        ->call('create')->assertHasNoFormErrors();
    expect(Announcement::where('slug', 'selected-image-draft')->sole()->hero_image_path)->toBe('images/updates/hero/hero.png');
});

it('escapes file names in thumbnail labels and excludes symlinks outside the image directory', function () {
    $name = 'image<img>.png';
    File::put(public_path('images/updates/hero/'.$name), 'fixture');
    File::put(public_path('outside.png'), 'fixture');
    symlink(public_path('outside.png'), public_path('images/updates/hero/link.png'));
    expect(app(AnnouncementImageService::class)->images('hero'))->not->toHaveKey('images/updates/hero/link.png');
    $label = view('filament.announcements.image-option', ['path' => 'images/updates/hero/'.$name, 'used' => false])->render();
    expect($label)->toContain('image&lt;img&gt;.png')->not->toContain('image<img>.png');
});

it('keeps each picker within its folder and leaves legacy assets outside both pickers', function () {
    File::put(public_path('images/updates/legacy.png'), 'fixture');
    expect(app(AnnouncementImageService::class)->images('social'))->toBe(['images/updates/social/unused.png' => false]);
    expect(app(AnnouncementImageService::class)->images('hero'))->not->toHaveKeys(['images/updates/legacy.png', 'images/updates/social/unused.png']);
    expect(fn () => app(AnnouncementImageService::class)->images('../'))->toThrow(InvalidArgumentException::class);

    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    Livewire::test(CreateAnnouncement::class)
        ->callAction(TestAction::make('browseImages')->schemaComponent('hero_image_path'), data: ['image' => 'images/updates/social/unused.png'])
        ->assertHasActionErrors(['image'])->assertSet('data.hero_image_path', null);
});

it('explains the empty folder without offering legacy images', function () {
    File::deleteDirectory(public_path('images/updates/hero'));
    File::ensureDirectoryExists(public_path('images/updates/hero'));
    File::put(public_path('images/updates/legacy.png'), 'fixture');
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $page = Livewire::test(CreateAnnouncement::class)
        ->mountAction(TestAction::make('browseImages')->schemaComponent('hero_image_path'));
    $html = $page->instance()->getSchema('mountedActionSchema0')->toHtml();
    expect($html)->toContain('No matching images in images/updates/hero.')
        ->not->toContain('images/updates/legacy.png');
});

it('checks image availability and empty folders without querying announcements', function () {
    File::deleteDirectory(public_path('images/updates/social'));
    File::ensureDirectoryExists(public_path('images/updates/social'));
    File::put(public_path('images/updates/social/.gitkeep'), '');
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(app(AnnouncementImageService::class)->hasImages('hero'))->toBeTrue();
    expect(app(AnnouncementImageService::class)->hasImages('social'))->toBeFalse();
    expect(app(AnnouncementImageService::class)->images('social'))->toBe([]);
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

it('reuses one usage scan across folders and filters and refreshes it for a new lifecycle', function () {
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/updates/hero/hero.png']);
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(app(AnnouncementImageService::class)->images('hero'))->toHaveKey('images/updates/hero/hero.png', true);
    expect(app(AnnouncementImageService::class)->images('hero', true))->not->toHaveKey('images/updates/hero/hero.png');
    expect(app(AnnouncementImageService::class)->images('social'))->toBe(['images/updates/social/unused.png' => false]);
    expect(DB::getQueryLog())->toHaveCount(1);
    DB::disableQueryLog();

    $draft->update(['hero_image_path' => 'images/updates/hero/unused.png']);
    app()->forgetScopedInstances();
    expect(app(AnnouncementImageService::class)->images('hero', true))->toHaveKey('images/updates/hero/hero.png')
        ->not->toHaveKey('images/updates/hero/unused.png');
});
