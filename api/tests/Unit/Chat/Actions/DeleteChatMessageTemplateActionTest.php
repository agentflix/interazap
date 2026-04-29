<?php

declare(strict_types=1);

use Domain\Chat\Actions\DeleteChatMessageTemplateAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gateway.url', 'http://gateway.test');
    config()->set('gateway.secret', 'secret-key');

    $this->tenant = PlatformTenant::factory()->create();
    $this->action = new DeleteChatMessageTemplateAction;
});

it('soft delete templates locais sem chamar Gateway', function (): void {
    Http::fake();

    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'local',
        'name' => 'tpl_local',
    ]);

    $this->action->execute($template);

    expect(ChatMessageTemplate::query()->find($template->id))->toBeNull();
    Http::assertNothingSent();
});

it('chama Gateway DELETE em template Meta e soft delete em sucesso', function (): void {
    Http::fake([
        '*/channels/*/templates/*' => Http::response(['success' => true], 200),
    ]);

    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
    ]);

    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $instance->id,
        'provider' => 'meta',
        'name' => 'meta_tpl',
        'language' => 'pt_BR',
        'status' => 'approved',
    ]);

    $this->action->execute($template);

    expect(ChatMessageTemplate::query()->find($template->id))->toBeNull();

    Http::assertSent(function ($request) use ($instance): bool {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), '/channels/'.$instance->id.'/templates/meta_tpl')
            && $request->header('x-api-key') === ['secret-key'];
    });
});

it('marca template Meta como disabled e soft delete quando Gateway falha', function (): void {
    Http::fake([
        '*/channels/*/templates/*' => Http::response(['error' => 'fail'], 500),
    ]);

    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
    ]);

    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $instance->id,
        'provider' => 'meta',
        'name' => 'meta_tpl',
        'language' => 'pt_BR',
        'status' => 'approved',
    ]);

    $this->action->execute($template);

    $trashed = ChatMessageTemplate::query()
        ->withTrashed()
        ->find($template->id);

    expect($trashed)->not->toBeNull()
        ->and($trashed->status)->toBe('disabled')
        ->and($trashed->trashed())->toBeTrue();
});
