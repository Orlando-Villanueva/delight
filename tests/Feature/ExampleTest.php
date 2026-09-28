<?php

test('the landing page presents the current product messaging and announcements link', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('href="'.route('announcements.index').'"', false)
        ->assertSeeText('66-book canon by default')
        ->assertSeeText('optional Catholic 73-book deuterocanonical support')
        ->assertSeeText('Opt into native notifications in Delight for Android.')
        ->assertSeeText('available in beta on supported devices.')
        ->assertSeeTextInOrder([
            'Keep your Bible reading going, wherever you read',
            'Your reading, clearly recorded',
            'Tools for your reading routine',
            'Book Completion Grid',
            'optional Catholic 73-book deuterocanonical support',
            'Achievements',
            'Reading Reminders',
        ]);
});
