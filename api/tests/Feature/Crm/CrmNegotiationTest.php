<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Enums\CRMTaskStatus;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    $this->funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);
    $this->contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('can list negotiations', function (): void {
    CRMNegotiation::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
    ]);

    $response = $this->getJson('/api/crm/negotiations');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can create negotiation', function (): void {
    $payload = [
        'title' => 'New Deal',
        'amount' => 5000.00,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_contact_id' => $this->contact->id,
    ];

    $response = $this->postJson('/api/crm/negotiations', $payload);

    $response->assertCreated()
        ->assertJsonFragment(['title' => 'New Deal']);

    $this->assertDatabaseHas('crm_negotiations', [
        'tenant_id' => $this->tenant->id,
        'title' => 'New Deal',
        'amount' => 5000.00,
        'status' => 'open',
    ]);
});

test('mark won sets closed_at', function (): void {
    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'status' => 'open',
    ]);

    $response = $this->postJson("/api/crm/negotiations/{$negotiation->id}/won");

    $response->assertOk();

    $negotiation->refresh();
    expect($negotiation->status->value)->toBe('won');
    expect($negotiation->closed_at)->not->toBeNull();
});

test('mark lost requires reason', function (): void {
    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'status' => 'open',
    ]);

    $response = $this->postJson("/api/crm/negotiations/{$negotiation->id}/lost", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['crm_reason_loss_id']);
});

test('mark lost sets closed_at and reason', function (): void {
    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'status' => 'open',
    ]);

    $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->postJson("/api/crm/negotiations/{$negotiation->id}/lost", [
        'crm_reason_loss_id' => $reason->id,
    ]);

    $response->assertOk();

    $negotiation->refresh();
    expect($negotiation->status->value)->toBe('lost');
    expect($negotiation->closed_at)->not->toBeNull();
    expect($negotiation->crm_reason_loss_id)->toBe($reason->id);
});

test('reopen clears closed_at and reason', function (): void {
    $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenant->id]);

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'status' => 'lost',
        'closed_at' => now(),
        'crm_reason_loss_id' => $reason->id,
    ]);

    $response = $this->postJson("/api/crm/negotiations/{$negotiation->id}/reopen");

    $response->assertOk();

    $negotiation->refresh();
    expect($negotiation->status->value)->toBe('open');
    expect($negotiation->closed_at)->toBeNull();
    expect($negotiation->crm_reason_loss_id)->toBeNull();
});

test('move changes step', function (): void {
    $step2 = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
    ]);

    $response = $this->postJson("/api/crm/negotiations/{$negotiation->id}/move", [
        'crm_negotiation_funnel_step_id' => $step2->id,
    ]);

    $response->assertOk();

    $negotiation->refresh();
    expect($negotiation->crm_negotiation_funnel_step_id)->toBe($step2->id);
});

test('move updates position inside step', function (): void {
    $step2 = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);

    $first = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $step2->id,
        'position' => 1,
    ]);

    $second = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $step2->id,
        'position' => 2,
    ]);

    $moving = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'position' => 1,
    ]);

    $response = $this->postJson("/api/crm/negotiations/{$moving->id}/move", [
        'crm_negotiation_funnel_step_id' => $step2->id,
        'position' => 2,
    ]);

    $response->assertOk();

    $first->refresh();
    $second->refresh();
    $moving->refresh();

    expect($first->position)->toBe(1);
    expect($moving->position)->toBe(2);
    expect($second->position)->toBe(3);
});

test('reorder updates positions in batch', function (): void {
    $first = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'position' => 1,
    ]);

    $second = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'position' => 2,
    ]);

    $response = $this->postJson('/api/crm/negotiations/reorder', [
        'items' => [
            ['id' => $first->id, 'position' => 2],
            ['id' => $second->id, 'position' => 1],
        ],
    ]);

    $response->assertOk();

    $first->refresh();
    $second->refresh();

    expect($first->position)->toBe(2);
    expect($second->position)->toBe(1);
});

test('cannot access other tenant negotiations', function (): void {
    $otherTenant = PlatformTenant::factory()->create();
    $otherFunnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherStep = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $otherTenant->id,
        'crm_negotiation_funnel_id' => $otherFunnel->id,
    ]);

    $otherNegotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $otherTenant->id,
        'crm_negotiation_funnel_id' => $otherFunnel->id,
        'crm_negotiation_funnel_step_id' => $otherStep->id,
    ]);

    $response = $this->getJson("/api/crm/negotiations/{$otherNegotiation->id}");

    $response->assertNotFound();
});

test('list supports advanced filters', function (): void {
    $companyA = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
    $companyB = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
    $contactB = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
    $tag = CRMTag::factory()->create([
        'tenant_id' => $this->tenant->id,
        'color' => '#123abc',
    ]);
    $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = CRMProduct::factory()->create(['tenant_id' => $this->tenant->id]);

    $matching = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_company_id' => $companyA->id,
        'crm_contact_id' => $this->contact->id,
        'amount' => 1500,
        'lead_score' => 80,
        'expected_close' => now()->addDays(10)->toDateString(),
        'status' => 'lost',
        'crm_reason_loss_id' => $reason->id,
    ]);
    DB::table('crm_negotiation_tags')->insert([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $matching->id,
        'crm_tag_id' => $tag->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    CRMNegotiationTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $matching->id,
        'auth_user_id' => $this->user->id,
        'status' => CRMTaskStatus::PENDING->value,
    ]);

    CRMNegotiationProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $matching->id,
        'crm_product_id' => $product->id,
    ]);

    CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_company_id' => $companyB->id,
        'crm_contact_id' => $contactB->id,
        'amount' => 100,
        'lead_score' => 20,
        'expected_close' => now()->addDays(40)->toDateString(),
        'status' => 'open',
    ]);

    $response = $this->getJson('/api/crm/negotiations?'
        .'company_id='.$companyA->id
        .'&amount_min=1000'
        .'&amount_max=2000'
        .'&lead_score_min=70'
        .'&lead_score_max=100'
        .'&expected_close_from='.urlencode(now()->addDays(5)->toDateString())
        .'&expected_close_to='.urlencode(now()->addDays(20)->toDateString())
        .'&tag_ids[]='.$tag->id
        .'&reason_loss_id='.$reason->id
        .'&has_pending_tasks=true'
        .'&product_id='.$product->id
        .'&user_id='.$this->user->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

test('kanban supports company and amount filters', function (): void {
    $companyA = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
    $companyB = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = CRMProduct::factory()->create(['tenant_id' => $this->tenant->id]);

    $matching = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_company_id' => $companyA->id,
        'amount' => 2000,
        'status' => 'open',
    ]);

    CRMNegotiationProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $matching->id,
        'crm_product_id' => $product->id,
    ]);

    CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_company_id' => $companyB->id,
        'amount' => 200,
        'status' => 'open',
    ]);

    $response = $this->getJson('/api/crm/negotiations-kanban?funnel_id='.$this->funnel->id
        .'&company_id='.$companyA->id
        .'&amount_min=1000');

    $response->assertOk();

    $steps = collect($response->json('data.steps', []));
    $items = collect($steps->flatMap(fn (array $step): array => $step['negotiations'] ?? []));

    expect($items)->toHaveCount(1);
    expect($items->first()['id'] ?? null)->toBe($matching->id);
});

test('kanban excludes negotiations with amount and no linked product', function (): void {
    $product = CRMProduct::factory()->create(['tenant_id' => $this->tenant->id]);

    $validNegotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'amount' => 1800,
        'status' => 'open',
    ]);

    CRMNegotiationProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $validNegotiation->id,
        'crm_product_id' => $product->id,
    ]);

    $invalidNegotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'amount' => 1200,
        'status' => 'open',
    ]);

    CRMNegotiationProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $invalidNegotiation->id,
        'crm_product_id' => null,
    ]);

    $zeroAmountNegotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'amount' => 0,
        'status' => 'open',
    ]);

    $response = $this->getJson('/api/crm/negotiations-kanban?funnel_id='.$this->funnel->id);

    $response->assertOk();

    $steps = collect($response->json('data.steps', []));
    $items = collect($steps->flatMap(fn (array $step): array => $step['negotiations'] ?? []));
    $ids = $items->pluck('id');

    expect($ids)->toContain($validNegotiation->id);
    expect($ids)->toContain($zeroAmountNegotiation->id);
    expect($ids)->not->toContain($invalidNegotiation->id);
});

test('list includes auth_user_id in response', function (): void {
    CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'auth_user_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/crm/negotiations');

    $response->assertOk();
    $firstItem = $response->json('data.0');
    expect($firstItem)->toHaveKey('auth_user_id');
    expect($firstItem['auth_user_id'])->toBe($this->user->id);
});

test('list includes user relationship in response', function (): void {
    CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'auth_user_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/crm/negotiations');

    $response->assertOk();
    $firstItem = $response->json('data.0');
    expect($firstItem)->toHaveKey('user');
    expect($firstItem['user'])->toBeArray();
    expect($firstItem['user']['id'])->toBe($this->user->id);
    expect($firstItem['user']['name'])->toBe($this->user->name);
});

test('list includes funnel and step relationships', function (): void {
    CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
    ]);

    $response = $this->getJson('/api/crm/negotiations');

    $response->assertOk();
    $firstItem = $response->json('data.0');
    expect($firstItem)->toHaveKey('funnel');
    expect($firstItem['funnel'])->toBeArray();
    expect($firstItem['funnel']['id'])->toBe($this->funnel->id);
    expect($firstItem['funnel']['name'])->toBe($this->funnel->name);

    expect($firstItem)->toHaveKey('step');
    expect($firstItem['step'])->toBeArray();
    expect($firstItem['step']['id'])->toBe($this->step->id);
    expect($firstItem['step']['name'])->toBe($this->step->name);
});

test('update accepts and persists notes field', function (): void {
    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_contact_id' => $this->contact->id,
    ]);

    $response = $this->putJson("/api/crm/negotiations/{$negotiation->id}", [
        'title' => $negotiation->title,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_contact_id' => $this->contact->id,
        'notes' => 'Observações importantes sobre esta negociação',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('crm_negotiations', [
        'id' => $negotiation->id,
        'notes' => 'Observações importantes sobre esta negociação',
    ]);
});

test('create accepts notes field', function (): void {
    $payload = [
        'title' => 'Deal with Notes',
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_contact_id' => $this->contact->id,
        'notes' => 'Notas iniciais da negociação',
    ];

    $response = $this->postJson('/api/crm/negotiations', $payload);

    $response->assertCreated();

    $this->assertDatabaseHas('crm_negotiations', [
        'title' => 'Deal with Notes',
        'notes' => 'Notas iniciais da negociação',
    ]);
});
