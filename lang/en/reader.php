<?php

return [
    'day_label' => ':label - Day :day',
    'testaments' => ['old' => 'Old Testament', 'new' => 'New Testament', 'deuterocanonical' => 'Deuterocanonical books'],
    'plan_messages' => [
        'subscribed' => "You've subscribed to :plan!",
        'unsubscribed' => 'You have unsubscribed from :plan.',
        'resumed' => "You've resumed :plan!",
    ],
    'errors' => [
        'subscription_missing' => 'Subscription not found.',
        'subscribe_first' => 'Subscribe to this plan to see your daily reading.',
        'plan_unavailable' => 'This plan is not available for your selected canon.',
        'inactive_subscription' => 'Cannot log to inactive subscription.',
        'before_tracking' => 'Cannot log a plan day from before tracking began.',
        'invalid_day' => 'Invalid plan day.',
        'day_complete' => 'Plan day already complete.',
        'enable_catholic_canon' => 'Enable deuterocanonical books in Settings to use this plan.',
        'invalid_chapter_range' => 'Invalid chapter range for the selected book.',
        'duplicate_reading' => 'You have already logged one or more of these chapters for :date.',
    ],
    'settings' => [
        'saved' => 'Settings saved.',
        'saved_plan_paused' => 'Settings saved. Your Catholic Canonical reading plan has been paused.',
    ],
    'feedback' => [
        'sent' => 'Thank you! Your feedback has been sent.',
        'failed' => "We couldn't send your feedback. Please try again.",
    ],
    'reading' => [
        'note_updated' => 'Reading note updated successfully.',
        'deleted' => 'Reading log deleted successfully.',
        'selected_deleted' => 'Selected readings deleted successfully.',
        'unauthorized_delete' => 'Unauthorized to delete this reading log.',
    ],
];
