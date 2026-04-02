<?php

declare(strict_types=1);

use Domain\CRM\Actions\CRMCompanyActions;
use Domain\CRM\DTOs\CRMCompanyDTO;
use Domain\CRM\Models\CRMCompany;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new CRMCompanyActions;
});

describe('list', function (): void {
    it('returns paginated companies for tenant', function (): void {
        CRMCompany::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(5);
    });

    it('excludes other tenant companies', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        CRMCompany::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CRMCompany::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->total())->toBe(3);
    });

    it('orders companies by name', function (): void {
        CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra Corp',
        ]);

        CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Inc',
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->first()->name)->toBe('Alpha Inc');
    });
});

describe('create', function (): void {
    it('creates a new company', function (): void {
        $dto = new CRMCompanyDTO(
            name: 'Acme Corp',
            document: '12345678901234',
            is_active: false,
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(CRMCompany::class)
            ->and($result->name)->toBe('Acme Corp')
            ->and($result->document)->toBe('12345678901234')
            ->and($result->is_active)->toBeFalse()
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });

    it('throws exception for duplicate company name', function (): void {
        $dto = new CRMCompanyDTO(
            name: 'Duplicate Corp',
        );

        $this->actions->create($this->tenant->id, $dto);

        expect(fn () => $this->actions->create($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('allows same name for different tenants', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        $dto = new CRMCompanyDTO(
            name: 'Shared Name Corp',
        );

        $result1 = $this->actions->create($this->tenant->id, $dto);
        $result2 = $this->actions->create($otherTenant->id, $dto);

        expect($result1->id)->not->toBe($result2->id);
    });
});

describe('update', function (): void {
    it('updates an existing company', function (): void {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);

        $dto = new CRMCompanyDTO(
            name: 'New Name',
            document: '98765432101234',
            is_active: false,
        );

        $result = $this->actions->update($this->tenant->id, $company->id, $dto);

        expect($result->name)->toBe('New Name')
            ->and($result->document)->toBe('98765432101234')
            ->and($result->is_active)->toBeFalse();
    });

    it('throws exception for non-existent company', function (): void {
        $dto = new CRMCompanyDTO(
            name: 'Test',
        );
        $nonExistentUuid = (string) \Illuminate\Support\Str::uuid();

        expect(fn () => $this->actions->update($this->tenant->id, $nonExistentUuid, $dto))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('delete', function (): void {
    it('deletes a company', function (): void {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->delete($this->tenant->id, $company->id);

        expect(\Domain\CRM\Models\CRMCompany::query()->find($company->id))->toBeNull();
    });

    it('throws exception for non-existent company', function (): void {
        $nonExistentUuid = (string) \Illuminate\Support\Str::uuid();
        expect(fn () => $this->actions->delete($this->tenant->id, $nonExistentUuid))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('find', function (): void {
    it('finds company by id', function (): void {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->find($this->tenant->id, $company->id);

        expect($result->id)->toBe($company->id);
    });

    it('throws exception for other tenant company', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $company = CRMCompany::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->find($this->tenant->id, $company->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    it('loads relationships', function (): void {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->find($this->tenant->id, $company->id);

        expect($result->relationLoaded('contacts'))->toBeTrue()
            ->and($result->relationLoaded('customFieldValues'))->toBeTrue();
    });
});
