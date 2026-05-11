<?php

declare(strict_types=1);

return [
    'stale_run_threshold_minutes' => (int) env('AI_STALE_RUN_THRESHOLD_MINUTES', 2),
    'autopilot' => [
        'max_tokens' => (int) env('AI_AUTOPILOT_MAX_TOKENS', 800),
        'max_tool_iterations' => (int) env('AI_MAX_TOOL_ITERATIONS', 5),
        'run_token_budget' => (int) env('AI_RUN_TOKEN_BUDGET', 32000),
        'compact_tool_results' => (bool) env('AI_COMPACT_TOOL_RESULTS', true),
        'queue_connection' => (string) env('AI_AUTOPILOT_QUEUE_CONNECTION', 'redis'),
        'queue_name' => (string) env('AI_AUTOPILOT_QUEUE_NAME', 'ai'),
    ],
    'autopilot_v2' => [
        'enabled' => (bool) env('AI_AUTOPILOT_CORE_V2_ENABLED', false),
        'rollout_percentage' => (int) env('AI_AUTOPILOT_CORE_V2_ROLLOUT_PERCENTAGE', 0),
        'tenant_allowlist' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('AI_AUTOPILOT_CORE_V2_TENANT_ALLOWLIST', ''))
        ))),
        'fail_closed' => (bool) env('AI_AUTOPILOT_CORE_V2_FAIL_CLOSED', true),
    ],
    'embedding' => [
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 512),
        'batch_size' => (int) env('AI_EMBEDDING_BATCH_SIZE', 100),
    ],
    'chunking' => [
        'chars_per_token' => (float) env('AI_CHUNKING_CHARS_PER_TOKEN', 3.5),
    ],
    'transcription' => [
        'queue_connection' => (string) env('AI_TRANSCRIPTION_QUEUE_CONNECTION', 'redis'),
        'queue_name' => (string) env('AI_TRANSCRIPTION_QUEUE_NAME', 'ai-transcription'),
        'whisper_model' => (string) env('AI_TRANSCRIPTION_WHISPER_MODEL', 'whisper-1'),
        'vision_model' => (string) env('AI_TRANSCRIPTION_VISION_MODEL', 'gpt-4o'),
        'vision_detail' => (string) env('AI_TRANSCRIPTION_VISION_DETAIL', 'low'),
        'vision_max_tokens' => (int) env('AI_TRANSCRIPTION_VISION_MAX_TOKENS', 300),
        'video_frame_interval_seconds' => (int) env('AI_TRANSCRIPTION_VIDEO_FRAME_INTERVAL', 5),
        'max_retries' => (int) env('AI_TRANSCRIPTION_MAX_RETRIES', 3),
        'retry_delay' => (int) env('AI_TRANSCRIPTION_RETRY_DELAY', 30),
        'timeout' => (int) env('AI_TRANSCRIPTION_TIMEOUT', 120),
    ],
    'budget' => [
        'default_daily_limit' => env('AI_BUDGET_DAILY_LIMIT') !== null
            ? (float) env('AI_BUDGET_DAILY_LIMIT')
            : null,
        'default_monthly_limit' => env('AI_BUDGET_MONTHLY_LIMIT') !== null
            ? (float) env('AI_BUDGET_MONTHLY_LIMIT')
            : null,
    ],
    'rag' => [
        'ef_search' => (int) env('RAG_EF_SEARCH', 100),
        'vector_weight' => (float) env('RAG_VECTOR_WEIGHT', 0.6),
        'keyword_weight' => (float) env('RAG_KEYWORD_WEIGHT', 0.4),
        'expand_neighbors' => (bool) env('RAG_EXPAND_NEIGHBORS', true),
        'neighbor_window' => (int) env('RAG_NEIGHBOR_WINDOW', 1),
    ],
];
