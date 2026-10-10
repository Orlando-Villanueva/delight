<?php

use App\Services\SentryEventSanitizer;

return [
    'dsn' => env('SENTRY_ENABLED', false) ? env('SENTRY_DSN') : null,
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('SENTRY_RELEASE'),
    'before_send' => [SentryEventSanitizer::class, 'sanitize'],
    'send_default_pii' => false,
    'max_request_body_size' => 'none',
    'max_breadcrumbs' => 0,
    'context_lines' => 0,
    'attach_stacktrace' => false,
    'spotlight' => false,
    'enable_logs' => false,
    'enable_metrics' => false,
    'enable_tracing' => false,
    'traces_sample_rate' => 0.0,
    'profiles_sample_rate' => 0.0,
    'http_connect_timeout' => 1,
    'http_timeout' => 2,
    'breadcrumbs' => [
        'logs' => false,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => false,
        'command_info' => false,
        'http_client_requests' => false,
        'notifications' => false,
    ],
    'tracing' => [
        'default_integrations' => false,
    ],
];
