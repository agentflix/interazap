<?php

declare(strict_types=1);

use Domain\Chat\Jobs\SubmitMetaTemplateJob;
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
        'category' => 'MARKETING',
        'status' => 'pending',
        'external_id' => null,
        'components_json' => [
            ['type' => 'BODY', 'text' => 'Olá {{1}}'],
        ],
    ]);
});

it('atualiza external_id e status em sucesso', function (): void {
    Http::fake([
        '*/channels/*/templates' => Http::response([
            'id' => 'meta_ext_999',
            'status' => 'PENDING',
        ], 200),
    ]);

    (new SubmitMetaTemplateJob((string) $this->template->id, (string) $this->template->tenant_id))->handle();

    $this->template->refresh();
    expect($this->template->external_id)->toBe('meta_ext_999')
        ->and($this->template->status)->toBe('pending');

    Http::assertSent(fn ($request) => $request->url() === 'http://gateway.test/channels/'.$this->instance->id.'/templates'
        && $request->hasHeader('x-api-key', 'secret-key')
        && $request['name'] === 'welcome_v1'
        && $request['language'] === 'pt_BR'
        && $request['category'] === 'MARKETING');
});

it('lança exceção quando gateway retorna erro HTTP', function (): void {
    Http::fake([
        '*/channels/*/templates' => Http::response(['error' => 'invalid'], 400),
    ]);

    (new SubmitMetaTemplateJob((string) $this->template->id, (string) $this->template->tenant_id))->handle();
})->throws(RuntimeException::class);

it('marca template como rejected em failed()', function (): void {
    $job = new SubmitMetaTemplateJob((string) $this->template->id, (string) $this->template->tenant_id);
    $job->failed(new RuntimeException('Meta API rejected: invalid components'));

    $this->template->refresh();
    expect($this->template->status)->toBe('rejected')
        ->and($this->template->rejected_reason)->toContain('invalid components');
});
