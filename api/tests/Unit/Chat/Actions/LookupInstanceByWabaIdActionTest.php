<?php

declare(strict_types=1);

use Domain\Chat\Actions\LookupInstanceByWabaIdAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new LookupInstanceByWabaIdAction;
    $this->tenant = PlatformTenant::factory()->create();
});

it('retorna tenant_id e instance_id quando encontra instância Meta ativa', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => true,
        'settings_json' => ['waba_id' => '123456789'],
    ]);

    $result = $this->action->execute('123456789');

    expect($result)->toBe([
        'tenant_id' => (string) $this->tenant->id,
        'instance_id' => (string) $instance->id,
    ]);
});

it('retorna null quando waba_id não corresponde a nenhuma instância', function (): void {
    ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => true,
        'settings_json' => ['waba_id' => 'OUTRO'],
    ]);

    expect($this->action->execute('NAO_EXISTE'))->toBeNull();
});

it('ignora instâncias de outros providers mesmo com mesmo waba_id', function (): void {
    ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'uazapi',
        'is_active' => true,
        'settings_json' => ['waba_id' => '999'],
    ]);

    expect($this->action->execute('999'))->toBeNull();
});

it('ignora instâncias inativas', function (): void {
    ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => false,
        'settings_json' => ['waba_id' => 'INACTIVE'],
    ]);

    expect($this->action->execute('INACTIVE'))->toBeNull();
});

it('retorna a primeira instância ativa quando múltiplas compartilham o mesmo waba_id', function (): void {
    $first = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => true,
        'settings_json' => ['waba_id' => 'SHARED'],
        'created_at' => now()->subMinutes(10),
    ]);

    ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => true,
        'settings_json' => ['waba_id' => 'SHARED'],
        'created_at' => now(),
    ]);

    $result = $this->action->execute('SHARED');

    expect($result)->not->toBeNull()
        ->and($result['instance_id'])->toBe((string) $first->id);
});
