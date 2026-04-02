<?php

declare(strict_types=1);

use Domain\CRM\Actions\CRMTagActions;
use Domain\CRM\DTOs\CRMTagDTO;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new CRMTagActions;
});

describe('list', function (): void {
    it('returns paginated tags for tenant', function (): void {
        CRMTag::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(5);
    });

    it('excludes other tenant tags', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        CRMTag::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CRMTag::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->total())->toBe(3);
    });

    it('orders tags by name', function (): void {
        CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra',
        ]);

        CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha',
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->first()->name)->toBe('Alpha');
    });
});

describe('create', function (): void {
    it('creates a new tag', function (): void {
        $dto = new CRMTagDTO(
            name: 'VIP',
            color: '#FF0000',
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(CRMTag::class)
            ->and($result->name)->toBe('VIP')
            ->and($result->color)->toBe('#FF0000')
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });

    it('throws exception for duplicate tag name', function (): void {
        $dto = new CRMTagDTO(
            name: 'Duplicate',
            color: '#000000',
        );

        $this->actions->create($this->tenant->id, $dto);

        expect(fn () => $this->actions->create($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('allows same name for different tenants', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        $dto = new CRMTagDTO(
            name: 'Shared Name',
            color: '#0000FF',
        );

        $result1 = $this->actions->create($this->tenant->id, $dto);
        $result2 = $this->actions->create($otherTenant->id, $dto);

        expect($result1->id)->not->toBe($result2->id);
    });
});

describe('delete', function (): void {
    it('deletes a tag', function (): void {
        $tag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->delete($this->tenant->id, $tag->id);

        expect(\Domain\CRM\Models\CRMTag::query()->find($tag->id))->toBeNull();
    });

    it('throws exception when trying to delete other tenant tag', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $tag = CRMTag::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->delete($this->tenant->id, $tag->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('attachToContact', function (): void {
    it('attaches tag to contact', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $tag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->attachToContact($this->tenant->id, $contact->id, $tag->id);

        expect($contact->tags()->where('crm_tags.id', $tag->id)->exists())->toBeTrue();
    });

    it('throws exception for non-existent contact', function (): void {
        $tag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $nonExistentUuid = (string) \Illuminate\Support\Str::uuid();

        expect(fn () => $this->actions->attachToContact($this->tenant->id, $nonExistentUuid, $tag->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('attachToCompany', function (): void {
    it('attaches tag to company', function (): void {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $tag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->attachToCompany($this->tenant->id, $company->id, $tag->id);

        expect($company->tags()->where('crm_tags.id', $tag->id)->exists())->toBeTrue();
    });

    it('throws exception for non-existent company', function (): void {
        $tag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $nonExistentUuid = (string) \Illuminate\Support\Str::uuid();

        expect(fn () => $this->actions->attachToCompany($this->tenant->id, $nonExistentUuid, $tag->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('attachToNegotiation', function (): void {
    it('attaches tag to negotiation', function (): void {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $tag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->attachToNegotiation($this->tenant->id, $negotiation->id, $tag->id);

        expect($negotiation->tags()->where('crm_tags.id', $tag->id)->exists())->toBeTrue();
    });

    it('throws exception for non-existent negotiation', function (): void {
        $tag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $nonExistentUuid = (string) \Illuminate\Support\Str::uuid();

        expect(fn () => $this->actions->attachToNegotiation($this->tenant->id, $nonExistentUuid, $tag->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
