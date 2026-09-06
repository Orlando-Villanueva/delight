<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('href="'.route('announcements.index').'"', false)
        ->assertSeeText('66-book canon by default')
        ->assertSeeText('optional Catholic 73-book deuterocanonical support')
        ->assertSeeTextInOrder([
            'A Bible reading tracker that fits the way you already read',
            'Everything You Need to Stay Consistent',
            'Book Completion Grid',
            'optional Catholic 73-book deuterocanonical support',
        ]);
});
