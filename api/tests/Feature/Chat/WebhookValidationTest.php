<?php

declare(strict_types=1);

use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Models\PlatformTenant;

use function Pest\Laravel\postJson;

it('rejects webhook payloads with invalid message types', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $payload = [
        'EventType' => 'messages',
        'message' => [
            'id' => 'msg-1',
            'chatid' => 'chat-1',
            'type' => 'malicious_type',
            'body' => 'Ping',
        ],
    ];

    postJson("/api/webhooks/uazapi/instances/{$instance->webhook_token}", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message.type']);
});
