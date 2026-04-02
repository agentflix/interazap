<?php

declare(strict_types=1);

/**
 * Sentry Configuration
 *
 * Error tracking and performance monitoring configuration.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Sentry DSN
    |--------------------------------------------------------------------------
    | The DSN tells the SDK where to send the events. If this value is not
    | provided, the SDK will try to read it from the SENTRY_DSN environment
    | variable.
    */

    'dsn' => env('SENTRY_DSN'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | The environment name used for Sentry events.
    */

    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Release
    |--------------------------------------------------------------------------
    | The release version for tracking deployments.
    */

    'release' => env('SENTRY_RELEASE', env('APP_VERSION', '1.0.0')),

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    | Breadcrumb recording configuration.
    */

    'breadcrumbs' => [
        // Capture Laravel logs as breadcrumbs
        'logs' => true,

        // Capture SQL queries as breadcrumbs
        'sql_queries' => true,

        // Capture bindings in SQL queries
        'sql_bindings' => false,

        // Capture queue job information
        'queue_info' => true,

        // Capture command information
        'command_info' => true,

        // Capture HTTP client requests
        'http_client_requests' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing
    |--------------------------------------------------------------------------
    | Performance monitoring configuration.
    */

    'tracing' => [
        // Enable tracing
        'enabled' => env('SENTRY_TRACES_SAMPLE_RATE', 0.0) > 0,

        // Trace sample rate (0.0 to 1.0)
        'sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

        // Queue job tracing
        'queue_job_transactions' => true,

        // Console command tracing
        'console_commands' => true,

        // Missing routes (404) tracing
        'missing_routes' => false,

        // Views that should not be traced
        'views_excluded' => [
            // 'email.*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    | Profiling configuration (requires tracing to be enabled).
    */

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Send Default PII
    |--------------------------------------------------------------------------
    | If enabled, user IPs and user data will be attached to events.
    */

    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    /*
    |--------------------------------------------------------------------------
    | Controllers Base Namespace
    |--------------------------------------------------------------------------
    | Namespace prefix for transaction names.
    */

    'controllers_base_namespace' => env('SENTRY_CONTROLLERS_BASE_NAMESPACE', 'Domain\\'),

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    | Exceptions that should not be reported to Sentry.
    */

    'dont_report' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Before Send Callback
    |--------------------------------------------------------------------------
    | Callback for modifying events before they are sent.
    | Return null to discard the event.
    */

    'before_send' => null,

    /*
    |--------------------------------------------------------------------------
    | Before Send Transaction Callback
    |--------------------------------------------------------------------------
    | Callback for modifying transactions before they are sent.
    */

    'before_send_transaction' => null,

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    | Additional tags to attach to all events.
    */

    'tags' => [
        'app_name' => env('APP_NAME', 'AgentFlix'),
    ],

];
