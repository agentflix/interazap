<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatWebhookIngestor;
use Domain\Chat\Models\ChatInstance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatWebhookMediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ingest_audio_and_location_store_media_fields(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;
        ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'uazapi',
            'webhook_token' => 'tok-123',
        ]);

        // Audio message
        $audioPayload = [
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'audio-1',
                'chatid' => '5511999999999@wa',
                'body' => null,
                'type' => 'audioMessage',
                'mediaUrl' => 'https://example.com/audio.ogg',
                'mimetype' => 'audio/ogg',
                'fileName' => 'audio.ogg',
                'fileSize' => 1234,
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $audioPayload);

        // Location message
        $locationPayload = [
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'loc-1',
                'chatid' => '5511999999999@wa',
                'body' => 'Local',
                'type' => 'location',
                'location' => ['lat' => -23.5, 'lng' => -46.6, 'name' => 'Escritorio'],
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $locationPayload);

        // Video keeps its media type.
        $videoPayload = [
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'vid-1',
                'chatid' => '5511999999999@wa',
                'type' => 'video',
                'mediaUrl' => 'https://example.com/video.mp4',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $videoPayload);

        $this->assertDatabaseHas('chat_messages', [
            'external_id' => 'audio-1',
            'type' => 'audio',
        ]);

        $audioMessage = \Domain\Chat\Models\ChatMessage::query()
            ->where('external_id', 'audio-1')
            ->first();

        $this->assertDatabaseHas('chat_messages_extended', [
            'message_id' => $audioMessage->id,
            'file_url' => 'https://example.com/audio.ogg',
            'mime_type' => 'audio/ogg',
            'file_name' => 'audio.ogg',
            'file_size' => 1234,
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'external_id' => 'loc-1',
            'type' => 'location',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'external_id' => 'vid-1',
            'type' => 'video',
        ]);

        $videoMessage = \Domain\Chat\Models\ChatMessage::query()
            ->where('external_id', 'vid-1')
            ->first();

        $this->assertDatabaseHas('chat_messages_extended', [
            'message_id' => $videoMessage->id,
            'file_url' => 'https://example.com/video.mp4',
        ]);
    }
}
