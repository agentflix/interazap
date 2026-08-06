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

it('rejeita segunda instância Meta ativa com o mesmo waba_id (fail-closed 3.2.1)', function (): void {
    $first = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => true,
        'settings_json' => ['waba_id' => 'SHARED'],
        'created_at' => now()->subMinutes(10),
    ]);

    // A migration 3.2.1 (uq_chat_instances_meta_waba_active) impede configuração
    // Meta ambígua: o banco REJEITA a segunda instância ativa com o mesmo waba_id.
    // SAVEPOINT para a transação do teste não ficar aborted após a violação.
    \Illuminate\Support\Facades\DB::statement('SAVEPOINT before_conflict');
    try {
        ChatInstance::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'provider' => 'meta',
            'is_active' => true,
            'settings_json' => ['waba_id' => 'SHARED'],
            'created_at' => now(),
        ]);
        $this->fail('Deveria lançar QueryException por violação do índice único');
    } catch (\Illuminate\Database\QueryException $exception) {
        \Illuminate\Support\Facades\DB::statement('ROLLBACK TO SAVEPOINT before_conflict');
        expect($exception->getMessage())->toContain('uq_chat_instances_meta_waba_active');
    }

    $result = $this->action->execute('SHARED');

    expect($result)->not->toBeNull()
        ->and($result['instance_id'])->toBe((string) $first->id);
});

it('permite instância inativa reutilizar o waba_id da ativa (unicidade só entre ativas)', function (): void {
    ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => false,
        'settings_json' => ['waba_id' => 'REUSED'],
    ]);

    ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => true,
        'settings_json' => ['waba_id' => 'REUSED'],
    ]);

    expect($this->action->execute('REUSED'))->not->toBeNull();
});
