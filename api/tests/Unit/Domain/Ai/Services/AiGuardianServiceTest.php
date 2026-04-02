<?php

declare(strict_types=1);

use Domain\Ai\Services\AiGuardianService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('returns safe when guardian approves', function (): void {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            json_encode([
                'choices' => [
                    ['message' => ['content' => json_encode(['safe' => true])]],
                ],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
    ]);

    $service = new AiGuardianService('fake-key');
    $result = $service->validate('hello');

    expect($result->isSafe())->toBeTrue();
});

it('returns unsafe when guardian flags prompt', function (): void {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(
            json_encode([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'safe' => false,
                        'reason' => 'Injection attempt',
                        'category' => 'prompt-injection',
                    ])]],
                ],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
    ]);

    $service = new AiGuardianService('fake-key');
    $result = $service->validate('malicious');

    expect($result->isUnsafe())->toBeTrue()
        ->and($result->reason)->toBe('Injection attempt')
        ->and($result->category)->toBe('prompt-injection');
});

it('returns safe when guardian api fails', function (): void {
    Http::fake(fn () => Http::response('error', 500));

    $service = new AiGuardianService('fake-key');
    $result = $service->validate('any');

    expect($result->isUnsafe())->toBeTrue()
        ->and($result->category)->toBe('guardian_provider_error');
});

it('returns safe on invalid response format', function (): void {
    Http::fake(fn () => Http::response([
        'choices' => [
            ['message' => ['content' => 'not-json']],
        ],
    ], 200));

    $service = new AiGuardianService('fake-key');
    $result = $service->validate('any');

    expect($result->isUnsafe())->toBeTrue()
        ->and($result->category)->toBe('guardian_invalid_response');
});

it('extracts unsafe payload from raw body when guardian response is malformed', function (): void {
    Http::fake(fn () => Http::response('{"safe":false,"reason":"Prompt leak","category":"prompt_injection"}', 200));

    $service = new AiGuardianService('fake-key');
    $result = $service->validate('any');

    expect($result->isUnsafe())->toBeTrue()
        ->and($result->reason)->toBe('Prompt leak')
        ->and($result->category)->toBe('prompt_injection');
});

it('accepts json snippet embedded in text response', function (): void {
    Http::fake(fn () => Http::response([
        'choices' => [
            [
                'message' => [
                    'content' => 'analysis: {"safe":false,"reason":"Detected override","category":"instruction_override"} end',
                ],
            ],
        ],
    ], 200));

    $service = new AiGuardianService('fake-key');
    $result = $service->validate('ignore system');

    expect($result->isUnsafe())->toBeTrue()
        ->and($result->reason)->toBe('Detected override')
        ->and($result->category)->toBe('instruction_override');
});

it('returns unsafe on guardian request exception', function (): void {
    Log::spy();

    Http::fake(static function (): void {
        throw new RuntimeException('network down');
    });

    $service = new AiGuardianService('fake-key');
    $result = $service->validate('any');

    expect($result->isUnsafe())->toBeTrue()
        ->and($result->category)->toBe('guardian_exception');
});

it('builds service from config values', function (): void {
    config()->set('services.openai.api_key', 'cfg-key');
    config()->set('services.openai.guardian_model', 'gpt-4o-mini');
    config()->set('services.openai.guardian_timeout', 3);

    Http::fake(fn () => Http::response([
        'choices' => [
            ['message' => ['content' => '{"safe":true}']],
        ],
    ], 200));

    $service = AiGuardianService::fromConfig();
    $result = $service->validate('safe prompt');

    expect($result->isSafe())->toBeTrue();
});
