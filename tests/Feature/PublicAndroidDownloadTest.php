<?php

const ANDROID_APK_URL = 'https://github.com/Orlando-Villanueva/delight/releases/download/android-v0.1.0-10/delight-android.apk';
const ANDROID_APK_SHA256 = 'e0c466cc1b35bf4cad174d7cb6e268688612a8997ea80b13847508b7bfc2dcf3';
const ANDROID_RELEASE_URL = 'https://github.com/Orlando-Villanueva/delight/releases/tag/android-v0.1.0-10';

it('shows a truthful preparation state before the direct download is enabled', function () {
    config(['android_release.download_url' => null]);

    $response = $this->get(route('android.download'));

    $response->assertOk()
        ->assertSeeText('Delight for Android')
        ->assertSeeText('The direct download is being prepared.')
        ->assertDontSee('Download APK · 103 MB');
});

it('presents the accepted Android release with installation and support guidance', function () {
    config([
        'android_release.download_url' => ANDROID_APK_URL,
        'mail.support_address' => 'support@example.com',
    ]);

    $response = $this->get(route('android.download'));

    $response->assertOk()
        ->assertSee('href="'.ANDROID_APK_URL.'"', false)
        ->assertSeeText('Download APK · 103 MB')
        ->assertSeeText('Version 0.1.0 (10)')
        ->assertSeeText('Android™ 7.0+')
        ->assertSee('src="'.asset('images/android-robot-head.svg').'"', false)
        ->assertSee('href="'.ANDROID_RELEASE_URL.'"', false)
        ->assertSeeText('View this release on GitHub')
        ->assertSee('href="'.route('announcements.index').'"', false)
        ->assertSeeText('Updates')
        ->assertSeeText('Get Started')
        ->assertSeeText('Early access')
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

it('exposes the Android release from public discovery surfaces only when download is enabled', function () {
    config(['android_release.download_url' => null]);

    $this->get(route('landing'))
        ->assertSeeText('A public native iPhone or Android app is not currently available.')
        ->assertDontSee('href="'.route('android.download').'"', false);
    $this->get(route('sitemap'))
        ->assertDontSee(route('android.download'), false);

    config(['android_release.download_url' => ANDROID_APK_URL]);

    $this->get(route('landing'))
        ->assertSee('href="'.route('android.download').'"', false)
        ->assertSeeText('download the native app');
    $this->get(route('sitemap'))
        ->assertSee('<loc>'.route('android.download').'</loc>', false);
});
