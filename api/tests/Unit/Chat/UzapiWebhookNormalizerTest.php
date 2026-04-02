<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Enums\WebhookEventType;
use Domain\Shared\Infrastructure\WhatsApp\Normalizers\UzapiWebhookNormalizer;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class UzapiWebhookNormalizerTest extends TestCase
{
    public function test_supports_detects_uazapi_payloads(): void
    {
        $normalizer = new UzapiWebhookNormalizer;

        $this->assertTrue($normalizer->supports(['EventType' => 'messages']));
        $this->assertTrue($normalizer->supports(['message' => ['id' => 'msg-1']]));
        $this->assertFalse($normalizer->supports([]));
    }

    public function test_normalizes_inbound_text_message(): void
    {
        $payload = [
            'EventType' => 'messages',
            'message' => [
                'messageid' => '3B50879ADE161979CEB8',
                'chatid' => '5511999999999@s.whatsapp.net',
                'fromMe' => false,
                'messageTimestamp' => 1767792702000,
                'text' => 'Deu bom?',
                'type' => 'text',
                'senderName' => 'Rafael Silva',
                'isGroup' => false,
            ],
        ];

        $normalizer = new UzapiWebhookNormalizer;
        $dto = $normalizer->normalize($payload);

        $this->assertSame(WebhookEventType::MESSAGE, $dto->eventType);
        $this->assertSame('3B50879ADE161979CEB8', $dto->providerMessageId);
        $this->assertSame('5511999999999', $dto->phone);
        $this->assertSame('incoming', $dto->direction);
        $this->assertSame('Deu bom?', $dto->textContent);
        $this->assertSame('text', $dto->mediaType);
        $this->assertFalse($dto->isGroup);
        $this->assertTrue($dto->timestamp->eq(Date::createFromTimestampMs(1767792702000)));
    }

    public function test_normalizes_outbound_message(): void
    {
        $payload = [
            'EventType' => 'messages',
            'message' => [
                'id' => '5511:ABC',
                'chatid' => '5511999999999@s.whatsapp.net',
                'fromMe' => true,
                'messageTimestamp' => 1767792629000,
                'text' => 'Oi isso é um teste de webhook',
                'type' => 'text',
            ],
        ];

        $normalizer = new UzapiWebhookNormalizer;
        $dto = $normalizer->normalize($payload);

        $this->assertSame('outgoing', $dto->direction);
    }

    public function test_normalizes_group_media_message(): void
    {
        $payload = [
            'EventType' => 'messages',
            'message' => [
                'messageid' => 'MSG-IMG',
                'chatid' => '120363012345678901@g.us',
                'fromMe' => false,
                'messageTimestamp' => 1767793197000,
                'type' => 'image',
                'content' => [
                    'caption' => 'Foto',
                    'URL' => 'https://cdn.test/image.png',
                ],
            ],
        ];

        $normalizer = new UzapiWebhookNormalizer;
        $dto = $normalizer->normalize($payload);

        $this->assertTrue($dto->isGroup);
        $this->assertSame('120363012345678901', $dto->phone);
        $this->assertSame('image', $dto->mediaType);
        $this->assertSame('https://cdn.test/image.png', $dto->mediaUrl);
        $this->assertSame('Foto', $dto->textContent);
    }

    public function test_normalizes_message_status_update(): void
    {
        $payload = [
            'EventType' => 'messages_update',
            'event' => [
                'MessageIDs' => ['3B50879ADE161979CEB8'],
                'Type' => 'Delivered',
                'Timestamp' => 1767792630,
                'IsFromMe' => true,
                'Chat' => '5511999999999@s.whatsapp.net',
                'IsGroup' => false,
            ],
        ];

        $normalizer = new UzapiWebhookNormalizer;
        $dto = $normalizer->normalize($payload);

        $this->assertSame(WebhookEventType::MESSAGE_STATUS, $dto->eventType);
        $this->assertSame('3B50879ADE161979CEB8', $dto->providerMessageId);
        $this->assertSame('delivered', $dto->status);
        $this->assertSame('outgoing', $dto->direction);
        $this->assertTrue($dto->timestamp->eq(Date::createFromTimestamp(1767792630)));
    }

    public function test_normalizes_connection_event(): void
    {
        $payload = [
            'EventType' => 'connection',
            'owner' => '5511999999999',
            'instance' => [
                'status' => 'connected',
            ],
        ];

        $normalizer = new UzapiWebhookNormalizer;
        $dto = $normalizer->normalize($payload);

        $this->assertSame(WebhookEventType::CONNECTION, $dto->eventType);
        $this->assertSame('connected', $dto->status);
        $this->assertSame('5511999999999', $dto->phone);
        $this->assertSame('system', $dto->direction);
    }

    public function test_normalizes_presence_event(): void
    {
        $payload = [
            'EventType' => 'presence',
            'presence' => [
                'jid' => '5511999999999@s.whatsapp.net',
                'type' => 'available',
                'timestamp' => 1767794000,
            ],
        ];

        $normalizer = new UzapiWebhookNormalizer;
        $dto = $normalizer->normalize($payload);

        $this->assertSame(WebhookEventType::PRESENCE, $dto->eventType);
        $this->assertSame('available', $dto->status);
        $this->assertSame('5511999999999', $dto->phone);
        $this->assertTrue($dto->timestamp->eq(Date::createFromTimestamp(1767794000)));
    }
}
