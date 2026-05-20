<?php

use Tests\TestCase;

class BrandConsistencyTest extends TestCase
{
    public function test_app_name_displays_consistently()
    {
        // Test that the app name is properly configured
        $this->assertNotEmpty(config('app.name'));
    }

    public function test_welcome_page_shows_brand_name()
    {
        $response = $this->get('/');
        $response->assertSee(config('app.name'));
    }

    public function test_login_page_shows_brand_name()
    {
        $response = $this->get('/login');
        $response->assertSee(config('app.name'));
    }

    public function test_register_page_shows_brand_name()
    {
        $response = $this->get('/register');
        $response->assertSee(config('app.name'));
    }

    public function test_page_titles_include_brand_name()
    {
        $response = $this->get('/login');
        $response->assertSee('<title>'.config('app.name'), false);
    }

    public function test_landing_page_uses_consistent_public_brand_name()
    {
        $publicBrandName = 'Delight - Bible Reading Tracker';

        $response = $this->get('/');

        $response->assertSuccessful();
        $response->assertSee('<title>'.$publicBrandName.'</title>', false);
        $response->assertSee('<meta property="og:title" content="'.$publicBrandName.'">', false);
        $response->assertSee('<meta name="twitter:title" content="'.$publicBrandName.'">', false);
        $response->assertSee('"name": "'.$publicBrandName.'"', false);
    }

    public function test_landing_page_uses_versioned_brand_assets()
    {
        $assetVersion = config('app.asset_version');

        $response = $this->get('/');

        $response->assertSuccessful();
        $response->assertSee('href="'.asset('favicon-app.ico').'?v='.$assetVersion.'"', false);
        $response->assertSee('href="'.asset('images/app-icon-v2-192.png').'?v='.$assetVersion.'"', false);
        $response->assertSee('src="'.asset('images/logo-64.png').'?v='.$assetVersion.'"', false);
        $response->assertSee(asset('images/logo-64-2x.png').'?v='.$assetVersion, false);
    }

    public function test_landing_page_links_versioned_pwa_manifest()
    {
        $assetVersion = config('app.asset_version');

        $response = $this->get('/');

        $response->assertSuccessful();
        $response->assertSee('href="'.asset('pwa.webmanifest').'?v='.$assetVersion.'"', false);
    }

    public function test_service_worker_matches_versioned_static_asset_requests()
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString('const requestUrl = new URL(event.request.url);', $serviceWorker);
        $this->assertStringContainsString('STATIC_CACHE_URLS.includes(requestUrl.pathname)', $serviceWorker);
        $this->assertStringContainsString('caches.match(event.request, { ignoreSearch: true })', $serviceWorker);
    }

    public function test_service_worker_does_not_cache_pwa_manifest()
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringNotContainsString("'/site.webmanifest'", $serviceWorker);
        $this->assertStringNotContainsString("'/pwa.webmanifest'", $serviceWorker);
    }
}
