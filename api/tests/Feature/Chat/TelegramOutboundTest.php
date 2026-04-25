<?php

declare(strict_types=1);

use Domain\Chat\Services\ChatGatewayService;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Tests\Mocks\FakeGatewayHttpClient;

/*
|--------------------------------------------------------------------------
| TASK-T24 — Telegram Outbound Dispatch Tests
|--------------------------------------------------------------------------
| Testa o envio de mensagens outbound via provedor Telegram,
| verificando que o payload correto é enviado ao Gateway.
*/

it('sends text message via Telegram through gateway', function (): void {
    $fakeGateway = new FakeGatewayHttpClient;
    $fakeGateway->fake('POST', '/outbound/send', [
        'success' => true,
        'message_id' => 'tg-msg-42',
    ]);

    $this->app->instance(GatewayHttpClient::class, $fakeGateway);

    /** @var ChatGatewayService $service */
    $service = app(ChatGatewayService::class);

    $result = $service->sendOutboundMessage(
        provider: 'telegram',
        instanceToken: 'fake-bot-token',
        tenantId: 'tenant-uuid-1',
        instanceId: 'instance-uuid-1',
        payload: [
            'type' => 'text',
            'to' => '999888777',
            'text' => 'Olá do InteraZap!',
        ],
    );

    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('message_id', 'tg-msg-42');

    $calls = $fakeGateway->calls();
    expect($calls)->toHaveCount(1);
    expect($calls[0]['method'])->toBe('POST');
    expect($calls[0]['endpoint'])->toBe('/outbound/send');
    expect($calls[0]['payload']['provider'])->toBe('telegram');
    expect($calls[0]['payload']['instanceToken'])->toBe('fake-bot-token');
    expect($calls[0]['payload']['tenantId'])->toBe('tenant-uuid-1');
    expect($calls[0]['payload']['instanceId'])->toBe('instance-uuid-1');
    expect($calls[0]['payload']['type'])->toBe('text');
    expect($calls[0]['payload']['to'])->toBe('999888777');
    expect($calls[0]['payload']['text'])->toBe('Olá do InteraZap!');
});

it('routes telegram outbound through gateway /outbound/send endpoint', function (): void {
    $fakeGateway = new FakeGatewayHttpClient;
    $fakeGateway->fake('POST', '/outbound/send', [
        'success' => true,
        'message_id' => 'tg-msg-43',
    ]);

    $this->app->instance(GatewayHttpClient::class, $fakeGateway);

    /** @var ChatGatewayService $service */
    $service = app(ChatGatewayService::class);

    $service->sendOutboundMessage(
        provider: 'telegram',
        instanceToken: 'bot-token-123',
        tenantId: 'tenant-uuid-2',
        instanceId: 'instance-uuid-2',
        payload: [
            'type' => 'file',
            'to' => '111222333',
            'file_url' => 'https://example.com/doc.pdf',
            'caption' => 'Documento importante',
        ],
    );

    $calls = $fakeGateway->calls();
    expect($calls)->toHaveCount(1);

    // Confirma que a rota é /outbound/send (mesmo endpoint unificado)
    expect($calls[0]['endpoint'])->toBe('/outbound/send');
    expect($calls[0]['payload']['provider'])->toBe('telegram');
});
