<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatPresenceActions;
use Domain\Chat\DTOs\ChatPresenceDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ChatPresenceActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_presence_calls_gateway_with_capped_delay(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-5',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendPresence')
            ->once()
            ->with('tok-5', [
                'number' => '5511999999999',
                'presence' => 'composing',
                'delay' => 300000,
            ])
            ->andReturn(['ok' => true]);

        $actions = new ChatPresenceActions($gateway);
        $dto = ChatPresenceDTO::fromArray([
            'ticket_id' => $ticket->id,
            'presence' => 'composing',
            'delay' => 999999,
        ]);

        $result = $actions->send((string) $tenant->id, $dto);

        $this->assertTrue($result['ok']);
    }

    public function test_send_presence_throws_when_missing_token_or_number(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => null,
            'phone' => null,
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $actions = new ChatPresenceActions($gateway);

        $dto = ChatPresenceDTO::fromArray([
            'ticket_id' => $ticket->id,
            'presence' => 'composing',
        ]);

        $this->expectException(ValidationException::class);
        $actions->send((string) $tenant->id, $dto);
    }
}
