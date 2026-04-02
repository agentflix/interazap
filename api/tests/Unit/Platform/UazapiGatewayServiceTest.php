<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Services\UazapiGatewayService;
use Tests\Mocks\FakeGatewayHttpClient;
use Tests\TestCase;

class UazapiGatewayServiceTest extends TestCase
{
    public function test_delegates_calls_to_gateway_client(): void
    {
        $client = new FakeGatewayHttpClient;
        $service = new UazapiGatewayService($client);

        // Instance management
        $client->fake('POST', '/uazapi/instances', ['id' => '1']);
        $client->fake('GET', '/uazapi/instances', ['items' => []]);
        $client->fake('POST', '/uazapi/instances/tok/connect', ['qr' => 'x']);
        $client->fake('POST', '/uazapi/instances/tok/disconnect', ['ok' => true]);
        $client->fake('POST', '/uazapi/instances/tok/delete', ['deleted' => true]);
        $client->fake('GET', '/uazapi/instances/tok/status', ['status' => 'connected']);
        $client->fake('POST', '/uazapi/instances/tok/webhook', ['ok' => true]);

        // Messages
        $client->fake('POST', '/send/text', ['messageid' => 'm1']);
        $client->fake('POST', '/send/file', ['messageid' => 'm2']);
        $client->fake('POST', '/message/presence', ['ok' => true]);
        $client->fake('POST', '/chat/read', ['ok' => true]);
        $client->fake('POST', '/send/contact', ['messageid' => 'm3']);
        $client->fake('POST', '/send/location', ['messageid' => 'm4']);
        $client->fake('POST', '/message/react', ['ok' => true]);
        $client->fake('POST', '/message/edit', ['ok' => true]);
        $client->fake('POST', '/send/template', ['messageid' => 'm5']);
        $client->fake('POST', '/message/delete', ['ok' => true]);
        $client->fake('POST', '/message/download', ['fileURL' => 'x']);

        // Contacts - registrar duas respostas em ordem de uso
        $client->fake('POST', '/uazapi/instances/tok/contacts/list', ['ok' => true], once: true);
        $client->fake('POST', '/uazapi/instances/tok/contacts/list', ['contacts' => []], once: true);
        $client->fake('POST', '/uazapi/instances/tok/contacts', ['id' => 'c1']);
        $client->fake('POST', '/uazapi/instances/tok/contacts/remove', ['ok' => true]);
        $client->fake('GET', '/uazapi/instances/tok/contacts', ['contacts' => ['list']]);

        // Assert instance management
        $this->assertSame(['id' => '1'], $service->initInstance(['name' => 'A']));
        $this->assertSame(['items' => []], $service->listInstances());
        $this->assertSame(['qr' => 'x'], $service->connectInstance('tok', ['mode' => 'qr']));
        $this->assertSame(['ok' => true], $service->disconnectInstance('tok'));
        $this->assertSame(['deleted' => true], $service->deleteInstance('tok'));
        $this->assertSame(['status' => 'connected'], $service->status('tok'));
        $this->assertSame(['ok' => true], $service->configureWebhook('tok', ['url' => 'x']));

        // Assert messages
        $this->assertSame(['messageid' => 'm1'], $service->sendText('tok', ['number' => '1']));
        $this->assertSame(['ok' => true], $service->syncContactsList('tok', [['number' => '1', 'name' => 'A']]));
        $this->assertSame(['messageid' => 'm2'], $service->sendFile('tok', ['number' => '1']));
        $this->assertSame(['ok' => true], $service->sendPresence('tok', ['state' => 'composing']));
        $this->assertSame(['ok' => true], $service->markAsRead('tok', ['id' => 'c1']));

        // Assert contacts - listContacts com payload usa POST contacts/list
        $this->assertSame(['contacts' => []], $service->listContacts('tok', ['search' => 'a']));
        $this->assertSame(['id' => 'c1'], $service->addContact('tok', ['phone' => '1']));
        $this->assertSame(['ok' => true], $service->removeContact('tok', ['id' => 'c1']));

        // More messages
        $this->assertSame(['messageid' => 'm3'], $service->sendContact('tok', ['number' => '1']));
        $this->assertSame(['messageid' => 'm4'], $service->sendLocation('tok', ['number' => '1']));
        $this->assertSame(['ok' => true], $service->reactToMessage('tok', ['id' => 'm1']));
        $this->assertSame(['ok' => true], $service->editMessage('tok', ['id' => 'm1']));
        $this->assertSame(['messageid' => 'm5'], $service->sendTemplate('tok', ['number' => '1']));
        $this->assertSame(['ok' => true], $service->deleteMessage('tok', ['id' => 'm1']));
        $this->assertSame(['fileURL' => 'x'], $service->downloadMedia('tok', ['id' => 'm1']));

        // Assert listContacts without payload uses GET
        $this->assertSame(['contacts' => ['list']], $service->listContacts('tok'));

        // Validate call details
        $calls = $client->calls();
        $sendTextCall = $calls[7];
        $this->assertSame(['token' => 'tok'], $sendTextCall['headers']);
        $this->assertSame('/send/text', $sendTextCall['endpoint']);
        $this->assertSame(['number' => '1'], $sendTextCall['payload']);
    }
}
