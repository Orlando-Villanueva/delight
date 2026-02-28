<?php

return [
    'export_token' => env('ANALYTICS_EXPORT_TOKEN'),
    'snapshot_timezone' => env('ANALYTICS_SNAPSHOT_TIMEZONE', env('APP_TIMEZONE', 'America/New_York')),
    'schema_version' => 'admin_analytics_weekly_v1',
];
