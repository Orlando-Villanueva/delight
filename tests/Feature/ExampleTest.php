<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSeeText('66-book canon by default')
        ->assertSeeText('optional Catholic 73-book deuterocanonical support')
        ->assertSeeTextInOrder([
            'Keep Every Bible Reading Visible, Stay on Track',
            'Everything You Need to Stay Consistent',
            'Book Completion Grid',
            'optional Catholic 73-book deuterocanonical support',
        ]);
});
