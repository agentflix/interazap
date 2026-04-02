<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => 1,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Timeouts & Configuration
    |--------------------------------------------------------------------------
    |
    | Configure timeout and retry settings per queue priority level.
    | These settings are used by QueueHealthService for monitoring.
    |
    */

    'queues' => [
        'critical' => [
            'timeout' => (int) env('QUEUE_CRITICAL_TIMEOUT', 30),
            'tries' => (int) env('QUEUE_CRITICAL_TRIES', 5),
            'backoff' => [5, 15, 30, 60, 120],
        ],
        'high' => [
            'timeout' => (int) env('QUEUE_HIGH_TIMEOUT', 60),
            'tries' => (int) env('QUEUE_HIGH_TRIES', 3),
            'backoff' => [10, 30, 90],
        ],
        'default' => [
            'timeout' => (int) env('QUEUE_DEFAULT_TIMEOUT', 120),
            'tries' => (int) env('QUEUE_DEFAULT_TRIES', 3),
            'backoff' => [10, 60, 300],
        ],
        'low' => [
            'timeout' => (int) env('QUEUE_LOW_TIMEOUT', 300),
            'tries' => (int) env('QUEUE_LOW_TRIES', 2),
            'backoff' => [30, 120],
        ],
        'ai' => [
            'timeout' => (int) env('QUEUE_AI_TIMEOUT', 180),
            'tries' => (int) env('QUEUE_AI_TRIES', 3),
            'backoff' => [15, 60, 180],
        ],
        'media' => [
            'timeout' => (int) env('QUEUE_MEDIA_TIMEOUT', 300),
            'tries' => (int) env('QUEUE_MEDIA_TRIES', 3),
            'backoff' => [30, 120, 300],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Health Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure thresholds for queue health monitoring.
    |
    */

    'health' => [
        'max_queue_size' => (int) env('QUEUE_HEALTH_MAX_SIZE', 1000),
        'max_stuck_jobs' => (int) env('QUEUE_HEALTH_MAX_STUCK', 10),
        'stuck_threshold_seconds' => (int) env('QUEUE_HEALTH_STUCK_THRESHOLD', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
