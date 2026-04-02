<?php

declare(strict_types=1);

use Domain\Chat\DTOs\ChatWebhookEventDTO;

it('creates webhook event dto from normalized payload with defaults', function (): void {
    $dto = ChatWebhookEventDTO::fromNormalized([
        'instance_webhook_token' => 'token-123',
        'raw' => ['payload' => ['id' => 'raw-1']],
    ]);

    expect($dto->provider)->toBe('uazapi')
        ->and($dto->eventType)->toBeNull()
        ->and($dto->instanceWebhookToken)->toBe('token-123')
        ->and($dto->tenantId)->toBeNull()
        ->and($dto->instanceId)->toBeNull()
        ->and($dto->direction)->toBeNull()
        ->and($dto->message)->toBeNull()
        ->and($dto->chat)->toBeNull()
        ->and($dto->owner)->toBeNull()
        ->and($dto->baseUrl)->toBeNull()
        ->and($dto->raw)->toBe(['payload' => ['id' => 'raw-1']]);
});

it('exports webhook event dto to array', function (): void {
    $dto = ChatWebhookEventDTO::fromNormalized([
        'provider' => 'zapi',
        'event_type' => 'message',
        'instance_webhook_token' => 'token-xyz',
        'tenant_id' => 'tenant-1',
        'instance_id' => 'instance-1',
        'direction' => 'incoming',
        'message' => ['body' => 'Hello'],
        'chat' => ['id' => 'chat-1'],
        'owner' => 'owner-1',
        'base_url' => 'https://gateway.test',
        'raw' => ['source' => 'gateway'],
    ]);

    expect($dto->toArray())->toMatchArray([
        'provider' => 'zapi',
        'event_type' => 'message',
        'instance_webhook_token' => 'token-xyz',
        'tenant_id' => 'tenant-1',
        'instance_id' => 'instance-1',
        'direction' => 'incoming',
        'message' => ['body' => 'Hello'],
        'chat' => ['id' => 'chat-1'],
        'owner' => 'owner-1',
        'base_url' => 'https://gateway.test',
        'raw' => ['source' => 'gateway'],
    ]);
});
