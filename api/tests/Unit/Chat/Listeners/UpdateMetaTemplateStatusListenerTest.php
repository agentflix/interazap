<?php

declare(strict_types=1);

use Domain\Chat\Events\MetaTemplateStatusUpdated;
use Domain\Chat\Listeners\UpdateMetaTemplateStatusListener;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
    ]);
    $this->template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $this->instance->id,
        'provider' => 'meta',
        'name' => 'welcome_v1',
        'language' => 'pt_BR',
        'status' => 'pending',
        'external_id' => 'ext_welcome',
    ]);
    $this->listener = new UpdateMetaTemplateStatusListener;
});

it('atualiza status do template via external_id', function (): void {
    $event = new MetaTemplateStatusUpdated(
        tenantId: (string) $this->tenant->id,
        instanceId: (string) $this->instance->id,
        templateName: 'welcome_v1',
        language: 'pt_BR',
        status: 'APPROVED',
        externalId: 'ext_welcome',
    );

    $this->listener->handle($event);

    $this->template->refresh();
    expect($this->template->status)->toBe('approved')
        ->and($this->template->last_synced_at)->not->toBeNull();
});

it('faz fallback por nome+language quando external_id não bate', function (): void {
    $event = new MetaTemplateStatusUpdated(
        tenantId: (string) $this->tenant->id,
        instanceId: (string) $this->instance->id,
        templateName: 'welcome_v1',
        language: 'pt_BR',
        status: 'REJECTED',
        rejectedReason: 'Body too long',
    );

    $this->listener->handle($event);

    $this->template->refresh();
    expect($this->template->status)->toBe('rejected')
        ->and($this->template->rejected_reason)->toBe('Body too long');
});

it('não cria template quando não encontrado', function (): void {
    $event = new MetaTemplateStatusUpdated(
        tenantId: (string) $this->tenant->id,
        instanceId: (string) $this->instance->id,
        templateName: 'inexistente',
        language: 'en',
        status: 'APPROVED',
    );

    $this->listener->handle($event);

    expect(ChatMessageTemplate::query()->where('name', 'inexistente')->count())->toBe(0);
});
