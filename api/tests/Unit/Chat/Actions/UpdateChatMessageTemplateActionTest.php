<?php

declare(strict_types=1);

use Domain\Chat\Actions\UpdateChatMessageTemplateAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
    ]);

    $this->action = new UpdateChatMessageTemplateAction;
});

it('atualiza todos os campos quando provider é local', function (): void {
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'local',
        'name' => 'old',
        'shortcut' => '/old',
        'content' => 'old content',
        'category' => 'support',
        'is_active' => true,
    ]);

    $updated = $this->action->execute($template, [
        'name' => 'new',
        'shortcut' => '/new',
        'content' => 'new content',
        'category' => 'sales',
        'is_active' => false,
    ]);

    expect($updated->name)->toBe('new')
        ->and($updated->shortcut)->toBe('/new')
        ->and($updated->content)->toBe('new content')
        ->and($updated->category)->toBe('sales')
        ->and($updated->is_active)->toBeFalse();
});

it('aceita is_active e shortcut quando provider é meta', function (): void {
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $this->instance->id,
        'provider' => 'meta',
        'name' => 'meta_tpl',
        'language' => 'pt_BR',
        'shortcut' => '/old',
        'is_active' => true,
        'status' => 'approved',
    ]);

    $updated = $this->action->execute($template, [
        'is_active' => false,
        'shortcut' => '/novo_atalho',
    ]);

    expect($updated->is_active)->toBeFalse()
        ->and($updated->shortcut)->toBe('/novo_atalho')
        ->and($updated->name)->toBe('meta_tpl');
});

it('rejeita campos não editáveis em template Meta com 422', function (): void {
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $this->instance->id,
        'provider' => 'meta',
        'name' => 'meta_tpl',
        'language' => 'pt_BR',
        'content' => 'original',
        'status' => 'approved',
    ]);

    expect(fn () => $this->action->execute($template, [
        'name' => 'tentativa_de_renomear',
        'content' => 'novo conteúdo',
    ]))->toThrow(ValidationException::class);

    $template->refresh();
    expect($template->name)->toBe('meta_tpl')
        ->and($template->content)->toBe('original');
});

it('rejeita mesmo se incluir um campo permitido junto com proibido', function (): void {
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $this->instance->id,
        'provider' => 'meta',
        'name' => 'meta_tpl',
        'language' => 'pt_BR',
        'status' => 'approved',
    ]);

    expect(fn () => $this->action->execute($template, [
        'is_active' => false,
        'category' => 'sales',
    ]))->toThrow(ValidationException::class);
});
