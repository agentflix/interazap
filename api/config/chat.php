<?php

declare(strict_types=1);

return [
    'auto_close_minutes' => (int) env('CHAT_AUTO_CLOSE_MINUTES', 0),
    'evaluation_public_url' => env('CHAT_EVALUATION_PUBLIC_URL', 'http://localhost:4200'),
    'stream_consumer' => [
        'block_ms' => (int) env('CHAT_STREAM_BLOCK_MS', 50),
        'metrics_enabled' => (bool) env('CHAT_STREAM_METRICS_ENABLED', true),
        'metrics_window_size' => (int) env('CHAT_STREAM_METRICS_WINDOW_SIZE', 200),
    ],
];
