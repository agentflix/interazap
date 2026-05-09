<?php

declare(strict_types=1);

use Domain\Chat\Actions\CreateMetaTemplateAction;
use Domain\Chat\Jobs\SubmitMetaTemplateJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    $this->tenant = PlatformTenant::factory()->create();
    $this->instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
    ]);
    $this->action = new CreateMetaTemplateAction;
});

it('cria template Meta em status pending e dispatcha job', function (): void {
    $template = $this->action->execute((string) $this->tenant->id, [
        'chat_instance_id' => (string) $this->instance->id,
        'name' => 'welcome_v1',
        'language' => 'pt_BR',
        'category' => 'MARKETING',
        'components' => [
            ['type' => 'BODY', 'text' => 'Olá {{1}}, bem-vindo!'],
        ],
    ]);

    expect($template)->toBeInstanceOf(ChatMessageTemplate::class)
        ->and($template->provider)->toBe('meta')
        ->and($template->status)->toBe('pending')
        ->and($template->external_id)->toBeNull()
        ->and($template->name)->toBe('welcome_v1')
        ->and($template->language)->toBe('pt_BR')
        ->and($template->content)->toBe('Olá {{1}}, bem-vindo!')
        ->and($template->components_json)->toBeArray();

    Queue::assertPushed(SubmitMetaTemplateJob::class, function (SubmitMetaTemplateJob $job) use ($template): bool {
        $reflection = new ReflectionClass($job);
        $prop = $reflection->getProperty('templateId');

        return $prop->getValue($job) === (string) $template->id;
    });
});

it('rejeita quando a instância não pertence ao tenant ou não é Meta', function (): void {
    $other = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'uazapi',
    ]);

    $this->action->execute((string) $this->tenant->id, [
        'chat_instance_id' => (string) $other->id,
        'name' => 'foo',
        'language' => 'pt_BR',
        'category' => 'MARKETING',
        'components' => [['type' => 'BODY', 'text' => 'x']],
    ]);
})->throws(ValidationException::class);

it('gera shortcut automaticamente a partir do nome quando ausente', function (): void {
    $template = $this->action->execute((string) $this->tenant->id, [
        'chat_instance_id' => (string) $this->instance->id,
        'name' => 'Boas Vindas',
        'language' => 'pt_BR',
        'category' => 'UTILITY',
        'components' => [['type' => 'BODY', 'text' => 'Olá']],
    ]);

    expect($template->shortcut)->not->toBeEmpty();
});
