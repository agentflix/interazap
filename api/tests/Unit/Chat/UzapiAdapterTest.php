<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\DTOs\SendMediaPayloadDTO;
use Domain\Chat\DTOs\SendTextPayloadDTO;
use Domain\Chat\Enums\MediaType;
use Domain\Chat\Enums\MessageDeliveryStatus;
use Domain\Shared\Infrastructure\WhatsApp\Adapters\UzapiAdapter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UzapiAdapterTest extends TestCase
{
    public function test_send_text_maps_success_response(): void
    {
        $captured = null;

        Http::fake(function ($request) use (&$captured) {
            $captured = $request;

            return Http::response([
                'message_id' => 'msg-1',
                'status' => 'sent',
                'timestamp' => 1700000000000,
            ], 200);
        });

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');
        $payload = new SendTextPayloadDTO(
            phone: '5511999999999',
            message: 'Hello world',
        );

        $result = $adapter->sendText($payload);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('msg-1', $result->providerMessageId);
        $this->assertSame(MessageDeliveryStatus::SENT, $result->status);

        $expected = Date::createFromTimestampMs(1700000000000);
        $this->assertTrue($result->sentAt->eq($expected));

        $this->assertSame('https://api.test/send/text', $captured?->url());
        $this->assertSame('Bearer tok-1', $captured?->header('Authorization')[0] ?? null);
        $this->assertSame('5511999999999', $captured?->data()['number'] ?? null);
        $this->assertSame('Hello world', $captured?->data()['text'] ?? null);
    }

    public function test_send_text_maps_provider_error(): void
    {
        Http::fake([
            'https://api.test/send/text' => Http::response([
                'success' => false,
                'error' => ['code' => 'UAZ-01', 'message' => 'Falha'],
            ], 200),
        ]);

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');
        $payload = new SendTextPayloadDTO(
            phone: '5511999999999',
            message: 'Hello world',
        );

        $result = $adapter->sendText($payload);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('UAZ-01', $result->errorCode);
    }

    public function test_send_media_maps_success_response(): void
    {
        $captured = null;

        Http::fake(function ($request) use (&$captured) {
            $captured = $request;

            return Http::response([
                'message_id' => 'media-1',
                'status' => 'sent',
                'timestamp' => 1700000001000,
            ], 200);
        });

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');
        $payload = new SendMediaPayloadDTO(
            phone: '5511999999999',
            type: MediaType::IMAGE,
            media: 'https://cdn.test/image.png',
            caption: 'Legenda',
        );

        $result = $adapter->sendMedia($payload);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('media-1', $result->providerMessageId);
        $this->assertSame('https://api.test/send/file', $captured?->url());
        $this->assertSame('image', $captured?->data()['type'] ?? null);
        $this->assertSame('Legenda', $captured?->data()['text'] ?? null);
    }

    public function test_send_text_returns_failed_on_exception(): void
    {
        Http::fake([
            'https://api.test/send/text' => Http::response(['error' => 'fail'], 500),
        ]);

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');
        $payload = new SendTextPayloadDTO(
            phone: '5511999999999',
            message: 'Hello world',
        );

        $result = $adapter->sendText($payload);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('EXCEPTION', $result->errorCode);
    }

    public function test_mark_as_read_and_send_presence_return_true(): void
    {
        Http::fake([
            'https://api.test/chat/read' => Http::response(['success' => true], 200),
            'https://api.test/message/presence' => Http::response(['success' => true], 200),
        ]);

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');

        $this->assertTrue($adapter->markAsRead('5511999999999'));
        $this->assertTrue($adapter->sendPresence('5511999999999', 'composing'));
    }

    public function test_mark_as_read_and_send_presence_return_false_on_exception(): void
    {
        Http::fake([
            'https://api.test/chat/read' => Http::response(['error' => 'fail'], 500),
            'https://api.test/message/presence' => Http::response(['error' => 'fail'], 500),
        ]);

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');

        $this->assertFalse($adapter->markAsRead('5511999999999'));
        $this->assertFalse($adapter->sendPresence('5511999999999', 'composing'));
    }

    public function test_get_instance_status_maps_connected_state(): void
    {
        Http::fake([
            'https://api.test/instance/status' => Http::response([
                'status' => 'CONNECTED',
                'phone' => '5511999999999',
                'profileName' => 'AgentFlix',
            ], 200),
        ]);

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');
        $status = $adapter->getInstanceStatus();

        $this->assertTrue($status->connected);
        $this->assertSame('5511999999999', $status->phone);
        $this->assertSame('AgentFlix', $status->name);
    }

    public function test_check_number_exists_and_profile_picture(): void
    {
        Http::fake([
            'https://api.test/check-number' => Http::response(['exists' => true], 200),
            'https://api.test/profile/picture' => Http::response(['url' => 'https://img.test/avatar.png'], 200),
        ]);

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');

        $this->assertTrue($adapter->checkNumberExists('5511999999999'));
        $this->assertSame('https://img.test/avatar.png', $adapter->getProfilePicture('5511999999999'));
    }

    public function test_status_and_picture_return_defaults_on_exception(): void
    {
        Http::fake([
            'https://api.test/instance/status' => Http::response(['error' => 'fail'], 500),
            'https://api.test/profile/picture' => Http::response(['error' => 'fail'], 500),
            'https://api.test/check-number' => Http::response(['error' => 'fail'], 500),
        ]);

        $adapter = new UzapiAdapter('tok-1', 'https://api.test');

        $status = $adapter->getInstanceStatus();
        $this->assertFalse($status->connected);
        $this->assertFalse($adapter->checkNumberExists('5511999999999'));
        $this->assertNull($adapter->getProfilePicture('5511999999999'));
    }
}
