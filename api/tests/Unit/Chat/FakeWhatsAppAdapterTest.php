<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\DTOs\SendMediaPayloadDTO;
use Domain\Chat\DTOs\SendTextPayloadDTO;
use Domain\Chat\Enums\MediaType;
use Domain\Shared\Infrastructure\WhatsApp\Adapters\FakeWhatsAppAdapter;
use RuntimeException;
use Tests\TestCase;

class FakeWhatsAppAdapterTest extends TestCase
{
    public function test_sends_text_message_successfully(): void
    {
        $adapter = new FakeWhatsAppAdapter;

        $payload = new SendTextPayloadDTO(
            phone: '5511999999999',
            message: 'Hello world',
        );

        $result = $adapter->sendText($payload);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $adapter->getSentMessages());
        $adapter->assertSentTo('5511999999999');
    }

    public function test_can_simulate_failure(): void
    {
        $adapter = new FakeWhatsAppAdapter;
        $adapter->shouldFailNextWith('TIMEOUT');

        $payload = new SendTextPayloadDTO(
            phone: '5511999999999',
            message: 'Hello world',
        );

        $result = $adapter->sendText($payload);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('TIMEOUT', $result->errorCode);
    }

    public function test_supports_media_send_and_reset_helpers(): void
    {
        $adapter = new FakeWhatsAppAdapter;

        $mediaResult = $adapter->sendMedia(new SendMediaPayloadDTO(
            phone: '5511988887777',
            type: MediaType::IMAGE,
            media: 'https://example.com/file.jpg',
            caption: 'media caption',
        ));

        $this->assertTrue($mediaResult->isSuccess());
        $adapter->assertSentTo('5511988887777');

        $adapter->reset();
        $this->assertSame([], $adapter->getSentMessages());
    }

    public function test_exposes_basic_capabilities_and_profile_picture_rules(): void
    {
        $adapter = new FakeWhatsAppAdapter;

        $status = $adapter->getInstanceStatus();

        $this->assertSame('fake', $adapter->getProviderName());
        $this->assertTrue($adapter->markAsRead('chat-1'));
        $this->assertTrue($adapter->sendPresence('chat-1', 'composing'));
        $this->assertTrue($adapter->checkNumberExists('5511999999999'));
        $this->assertTrue($status->connected);
        $this->assertNull($adapter->getProfilePicture(''));
        $this->assertNotNull($adapter->getProfilePicture('5511999999999'));
    }

    public function test_assert_sent_to_throws_when_phone_was_not_sent(): void
    {
        $this->expectException(RuntimeException::class);

        $adapter = new FakeWhatsAppAdapter;
        $adapter->assertSentTo('5511000000000');
    }
}
