<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Actions\CRMNoteActions;
use Domain\CRM\DTOs\CRMNoteDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNote;
use Domain\Platform\Models\PlatformTenant;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actions = new CRMNoteActions;
});

describe('list', function (): void {
    it('returns paginated notes for entity', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CRMNote::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact->id,
            'auth_user_id' => $this->user->id,
        ]);

        $result = $this->actions->list($this->tenant->id, CRMContact::class, $contact->id);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(3);
    });

    it('filters by entity type and id', function (): void {
        $contact1 = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
        $contact2 = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        CRMNote::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact1->id,
            'auth_user_id' => $this->user->id,
        ]);

        CRMNote::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact2->id,
            'auth_user_id' => $this->user->id,
        ]);

        $result = $this->actions->list($this->tenant->id, CRMContact::class, $contact1->id);

        expect($result->total())->toBe(2);
    });

    it('excludes other tenant notes', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        CRMNote::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact->id,
            'auth_user_id' => $this->user->id,
        ]);

        CRMNote::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact->id,
            'auth_user_id' => AuthUser::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);

        $result = $this->actions->list($this->tenant->id, CRMContact::class, $contact->id);

        expect($result->total())->toBe(3);
    });

    it('orders notes by created_at desc', function (): void {
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        $older = CRMNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact->id,
            'auth_user_id' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        $newer = CRMNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact->id,
            'auth_user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $result = $this->actions->list($this->tenant->id, CRMContact::class, $contact->id);

        expect($result->first()->id)->toBe($newer->id);
    });

    it('loads author relation', function (): void {
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        CRMNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => CRMContact::class,
            'entity_id' => $contact->id,
            'auth_user_id' => $this->user->id,
        ]);

        $result = $this->actions->list($this->tenant->id, CRMContact::class, $contact->id);

        expect($result->first()->relationLoaded('author'))->toBeTrue();
    });
});

describe('create', function (): void {
    it('creates a note for contact', function (): void {
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        $dto = new CRMNoteDTO(
            entity_type: CRMContact::class,
            entity_id: $contact->id,
            content: 'Test note content',
        );

        $result = $this->actions->create($this->tenant->id, $this->user->id, $dto);

        expect($result)->toBeInstanceOf(CRMNote::class)
            ->and($result->content)->toBe('Test note content')
            ->and($result->entity_id)->toBe($contact->id)
            ->and($result->entity_type)->toBe(CRMContact::class)
            ->and($result->auth_user_id)->toBe($this->user->id)
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });

    it('creates a note for negotiation', function (): void {
        $negotiation = CRMNegotiation::factory()->create(['tenant_id' => $this->tenant->id]);

        $dto = new CRMNoteDTO(
            entity_type: CRMNegotiation::class,
            entity_id: $negotiation->id,
            content: 'Negotiation note content',
        );

        $result = $this->actions->create($this->tenant->id, $this->user->id, $dto);

        expect($result)->toBeInstanceOf(CRMNote::class)
            ->and($result->content)->toBe('Negotiation note content')
            ->and($result->entity_id)->toBe($negotiation->id)
            ->and($result->entity_type)->toBe(CRMNegotiation::class);
    });
});
