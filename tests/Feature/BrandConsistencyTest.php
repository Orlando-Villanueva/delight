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
}
