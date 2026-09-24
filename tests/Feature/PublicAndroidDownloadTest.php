<?php

const ANDROID_APK_URL = 'https://github.com/Orlando-Villanueva/delight/releases/download/android-v0.1.1-12/delight-android.apk';
const ANDROID_APK_SHA256 = '2a5c777e4057247c23220bc5178cd8c663279b76b1b3709ec8d5925df80ba2fe';
const ANDROID_RELEASE_URL = 'https://github.com/Orlando-Villanueva/delight/releases/tag/android-v0.1.1-12';
const ANDROID_UPDATE_URL = 'https://mydelight.app/android';

it('returns cacheable current Android release metadata without authentication', function () {
    $this->getJson('/api/v1/android/release')
        ->assertSuccessful()
        ->assertHeader('cache-control', 'max-age=300, public, s-maxage=300, stale-while-revalidate=600')
        ->assertJsonPath('data.version', '0.1.1')
        ->assertJsonPath('data.version_code', 12)
        ->assertJsonPath('data.update_url', ANDROID_UPDATE_URL);
});

it('presents the accepted Android release with installation and support guidance', function () {
    config([
        'mail.support_address' => 'support@example.com',
    ]);

    $response = $this->get(route('android.download'));

    $response->assertOk()
        ->assertSee('href="'.ANDROID_APK_URL.'"', false)
        ->assertSeeText('Download APK · 104 MB')
        ->assertSeeText('Version 0.1.1 (12)')
        ->assertSeeText('September 24, 2026')
        ->assertSeeText('Android™ 7.0+')
        ->assertSee('src="'.asset('images/android-robot-head.svg').'"', false)
        ->assertSee('href="'.ANDROID_RELEASE_URL.'"', false)
        ->assertSeeText('View this release on GitHub')
        ->assertSee('href="'.route('announcements.index').'"', false)
        ->assertSeeText('Updates')
        ->assertSeeText('Get Started')
        ->assertSeeText('Early access')
        ->assertSee('bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600', false)
        ->assertSee('grid-cols-[3.5rem_minmax(0,1fr)]', false)
        ->assertSeeText('Technical download details')
        ->assertSeeText('com.orlandovillanueva.delight')
        ->assertSeeText(ANDROID_APK_SHA256)
        ->assertSeeText('signed by Google Play App Signing')
        ->assertSeeText('allow your browser to install apps from this source')
        ->assertSeeText('does not update automatically yet')
        ->assertSeeText('Prefer installing through Google Play?')
        ->assertSeeText('Request a test invitation')
        ->assertDontSeeText('older Delight dogfood APK')
        ->assertSeeText('Android is a trademark of Google LLC.')
        ->assertSee('href="https://creativecommons.org/licenses/by/3.0/"', false)
        ->assertSee('href="'.route('privacy-policy').'"', false)
        ->assertDontSee('href="'.route('account-deletion.create').'"', false)
        ->assertSee('href="mailto:support@example.com?subject=Delight%20Android%20support"', false)
        ->assertSee('href="mailto:support@example.com?subject=Delight%20Google%20Play%20closed%20test"', false);
});

it('renders a dedicated Android social preview for link sharing', function () {
    $socialImageUrl = asset('images/android-social-preview.png');

    $response = $this->get(route('android.download'));

    $response
        ->assertSee('<link rel="canonical" href="'.route('android.download').'">', false)
        ->assertSee('<meta property="og:title" content="Delight for Android - Bible Reading Tracker">', false)
        ->assertSee('<meta property="og:image" content="'.$socialImageUrl.'">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:height" content="630">', false)
        ->assertSee('<meta property="og:image:type" content="image/png">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:image" content="'.$socialImageUrl.'">', false);

    expect(public_path('images/android-social-preview.png'))->toBeFile();
});

it('exposes the Android release from public discovery surfaces', function () {
    $this->get(route('landing'))
        ->assertSee('href="'.route('android.download').'"', false)
        ->assertSeeText('download the native app');
    $this->get(route('sitemap'))
        ->assertSee('<loc>'.route('android.download').'</loc>', false);
});
