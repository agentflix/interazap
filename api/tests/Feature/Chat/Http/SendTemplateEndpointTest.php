<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gateway.url', 'http://gateway.test');
    config()->set('gateway.secret', 'secret-key');
});

function makeAgentUser(): AuthUser
{
    $user = AuthUser::factory()->create();
    $perm = AuthPermission::query()->firstOrCreate(
        ['name' => 'chat.messages.create', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );
    $user->givePermissionTo($perm);

    return $user;
}

it('POST /api/chat/tickets/{id}/messages/template envia template e retorna 201', function (): void {
    Http::fake([
        '*/channels/*/send-template' => Http::response([
            'messageid' => 'wamid.HBgL_test',
        ], 200),
    ]);

    $user = makeAgentUser();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $user->tenant_id,
        'provider' => 'meta',
    ]);
    $contact = CRMContact::factory()->create([
        'tenant_id' => $user->tenant_id,
        'phone' => '5511999999999',
    ]);
    $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
        'instance_id' => $instance->id,
        'contact_id' => $contact->id,
        'phone_e164' => '5511999999999',
    ]);
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'chat_instance_id' => $instance->id,
        'provider' => 'meta',
        'name' => 'welcome_v1',
        'language' => 'pt_BR',
        'status' => 'approved',
        'components_json' => [
            ['type' => 'BODY', 'text' => 'Olá {{1}}'],
        ],
    ]);

    $this->actingAs($user, 'sanctum');

    $resp = $this->postJson('/api/chat/tickets/'.$ticket->id.'/messages/template', [
        'template_id' => (string) $template->id,
        'variables' => ['1' => 'Rafael'],
    ])->assertCreated()->json();

    expect($resp['data']['type'])->toBe('template')
        ->and($resp['data']['status'])->toBe('sent')
        ->and($resp['data']['external_id'])->toBe('wamid.HBgL_test');
});

it('valida payload — template_id é obrigatório e UUID', function (): void {
    $user = makeAgentUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id, 'provider' => 'meta']);
    $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
        'instance_id' => $instance->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/chat/tickets/'.$ticket->id.'/messages/template', [
        'template_id' => 'not-a-uuid',
    ])->assertStatus(422);
});
