<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Mockery;
use Tests\TestCase;

class ChatGatewayServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delegates_calls_to_uazapi_gateway(): void
    {
        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $gateway = Mockery::mock(GatewayHttpClient::class);
        $service = new ChatGatewayService($uazapi, $gateway);

        $token = 'token-1';
        $payload = ['number' => '5511999999999', 'text' => 'Olá'];
        $filePayload = ['number' => '5511999999999', 'file' => 'file-url'];
        $presencePayload = ['number' => '5511999999999', 'presence' => 'composing'];
        $contactPayload = ['number' => '5511999999999', 'fullName' => 'Contato', 'phoneNumber' => '5511'];
        $locationPayload = ['number' => '5511999999999', 'latitude' => -23.0, 'longitude' => -46.0];
        $reactionPayload = ['number' => '5511999999999', 'id' => 'msg-1', 'text' => '😀'];
        $editPayload = ['id' => 'msg-1', 'text' => 'Editada'];
        $templatePayload = ['number' => '5511999999999', 'templateId' => 'welcome'];
        $deletePayload = ['id' => 'msg-1'];

        $uazapi->shouldReceive('sendText')->once()->with($token, $payload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('sendFile')->twice()->with($token, $filePayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('sendPresence')->once()->with($token, $presencePayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('markAsRead')->once()->with($token, ['number' => '5511', 'read' => true])->andReturn(['ok' => true]);
        $uazapi->shouldReceive('sendContact')->once()->with($token, $contactPayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('sendLocation')->once()->with($token, $locationPayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('reactToMessage')->once()->with($token, $reactionPayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('editMessage')->once()->with($token, $editPayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('sendTemplate')->once()->with($token, $templatePayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('deleteMessage')->once()->with($token, $deletePayload)->andReturn(['ok' => true]);
        $uazapi->shouldReceive('downloadMedia')->once()->with($token, [
            'id' => 'msg-1',
            'return_link' => true,
            'return_base64' => false,
            'generate_mp3' => false,
            'transcribe' => false,
            'openai_apikey' => null,
            'download_quoted' => false,
        ])->andReturn(['fileURL' => 'http://files.test/file.jpg']);

        $this->assertSame(['ok' => true], $service->sendText($token, $payload));
        $this->assertSame(['ok' => true], $service->sendFile($token, $filePayload));
        $this->assertSame(['ok' => true], $service->sendAudio($token, $filePayload));
        $this->assertSame(['ok' => true], $service->sendPresence($token, $presencePayload));
        $this->assertSame(['ok' => true], $service->markAsRead($token, ['number' => '5511', 'read' => true]));
        $this->assertSame(['ok' => true], $service->sendContact($token, $contactPayload));
        $this->assertSame(['ok' => true], $service->sendLocation($token, $locationPayload));
        $this->assertSame(['ok' => true], $service->reactToMessage($token, $reactionPayload));
        $this->assertSame(['ok' => true], $service->editMessage($token, $editPayload));
        $this->assertSame(['ok' => true], $service->sendTemplate($token, $templatePayload));
        $this->assertSame(['ok' => true], $service->deleteMessage($token, $deletePayload));
        $this->assertSame(['fileURL' => 'http://files.test/file.jpg'], $service->downloadMedia($token, 'msg-1'));
    }

    public function test_sends_outbound_message_for_zapi(): void
    {
        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $gateway = Mockery::mock(GatewayHttpClient::class);
        $service = new ChatGatewayService($uazapi, $gateway);

        $gateway->shouldReceive('post')->once()->with('/outbound/send', [
            'provider' => 'zapi',
            'instanceToken' => 'instance-1:token-1',
            'tenantId' => 'tenant-1',
            'instanceId' => 'instance-uuid-1',
            'type' => 'text',
            'to' => '5511999999999',
            'text' => 'Olá',
        ])->andReturn(['success' => true]);

        $response = $service->sendOutboundMessage('zapi', 'instance-1:token-1', 'tenant-1', 'instance-uuid-1', [
            'type' => 'text',
            'to' => '5511999999999',
            'text' => 'Olá',
        ]);

        $this->assertSame(['success' => true], $response);
    }
}
