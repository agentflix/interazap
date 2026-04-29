<?php

declare(strict_types=1);

use Domain\Chat\Actions\SyncMetaTemplatesAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gateway.url', 'http://gateway.test');
    config()->set('gateway.secret', 'secret-key');

    $this->tenant = PlatformTenant::factory()->create();
    $this->instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
    ]);

    $this->action = new SyncMetaTemplatesAction;
});

function fakeMetaTemplatesResponse(array $templates): void
{
    Http::fake([
        '*/channels/*/templates*' => Http::response(['data' => $templates], 200),
    ]);
}

function metaTemplate(string $name, string $body, string $status = 'APPROVED', string $language = 'pt_BR'): array
{
    return [
        'name' => $name,
        'status' => $status,
        'category' => 'MARKETING',
        'language' => $language,
        'external_id' => 'ext_'.$name,
        'components' => [
            ['type' => 'BODY', 'text' => $body],
        ],
    ];
}

it('sincroniza 3 templates novos da Meta', function (): void {
    fakeMetaTemplatesResponse([
        metaTemplate('welcome', 'Olá {{1}}'),
        metaTemplate('reminder', 'Lembrete: {{1}}'),
        metaTemplate('promo', 'Promoção exclusiva!'),
    ]);

    $count = $this->action->execute(
        (string) $this->tenant->id,
        (string) $this->instance->id,
    );

    expect($count)->toBe(3);

    $templates = ChatMessageTemplate::query()
        ->where('chat_instance_id', $this->instance->id)
        ->where('provider', 'meta')
        ->get();

    expect($templates)->toHaveCount(3);

    $welcome = $templates->firstWhere('name', 'welcome');
    expect($welcome->status)->toBe('approved')
        ->and($welcome->language)->toBe('pt_BR')
        ->and($welcome->external_id)->toBe('ext_welcome')
        ->and($welcome->content)->toBe('Olá {{1}}')
        ->and($welcome->components_json)->toBeArray()
        ->and($welcome->last_synced_at)->not->toBeNull()
        ->and($welcome->is_active)->toBeTrue();
});

it('marca template removido como disabled quando não vem na resposta', function (): void {
    ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $this->instance->id,
        'provider' => 'meta',
        'name' => 'old_template',
        'language' => 'pt_BR',
        'status' => 'approved',
    ]);

    fakeMetaTemplatesResponse([
        metaTemplate('welcome', 'Olá'),
    ]);

    $count = $this->action->execute(
        (string) $this->tenant->id,
        (string) $this->instance->id,
    );

    expect($count)->toBe(1);

    $old = ChatMessageTemplate::query()->where('name', 'old_template')->first();
    expect($old->status)->toBe('disabled')
        ->and($old->last_synced_at)->not->toBeNull();
});

it('não toca em templates com provider local', function (): void {
    $local = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $this->instance->id,
        'provider' => 'local',
        'name' => 'local_template',
        'status' => 'approved',
        'language' => 'pt_BR',
    ]);

    fakeMetaTemplatesResponse([
        metaTemplate('welcome', 'Olá'),
    ]);

    $this->action->execute(
        (string) $this->tenant->id,
        (string) $this->instance->id,
    );

    $local->refresh();
    expect($local->provider)->toBe('local')
        ->and($local->status)->toBe('approved');
});

it('é idempotente — chamar 2x não duplica registros', function (): void {
    fakeMetaTemplatesResponse([
        metaTemplate('welcome', 'Olá {{1}}'),
        metaTemplate('reminder', 'Lembrete: {{1}}'),
    ]);

    $this->action->execute((string) $this->tenant->id, (string) $this->instance->id);
    $this->action->execute((string) $this->tenant->id, (string) $this->instance->id);

    $count = ChatMessageTemplate::query()
        ->where('chat_instance_id', $this->instance->id)
        ->where('provider', 'meta')
        ->count();

    expect($count)->toBe(2);
});

it('lança ModelNotFoundException quando a instância não é Meta', function (): void {
    $uazapi = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'uazapi',
    ]);

    $this->action->execute(
        (string) $this->tenant->id,
        (string) $uazapi->id,
    );
})->throws(ModelNotFoundException::class);
