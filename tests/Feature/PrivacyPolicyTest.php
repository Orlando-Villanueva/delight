<?php

it('describes the current data practices and account deletion path', function () {
    config(['mail.support_address' => 'privacy@example.com']);

    $response = $this->get(route('privacy-policy'));

    $response
        ->assertSee('Last updated: September 12, 2026')
        ->assertSee('Delight is intended for people aged 13 and older.')
        ->assertSee('securely hashed password')
        ->assertSee('first-party, account-linked or aggregated measurements')
        ->assertSee('Google, when you choose Google sign-in.')
        ->assertSee('including Laravel Cloud.')
        ->assertSee('including Mailgun.')
        ->assertSee('privacy@example.com')
        ->assertSee(route('account-deletion.create'), false)
        ->assertSee('Verified requests are normally completed within 30 days.')
        ->assertDontSee('orlandovillanueva11@gmail.com')
        ->assertDontSee('appropriate for users of all ages')
        ->assertDontSee('All passwords are encrypted')
        ->assertDontSee('IP addresses or location information');
});
