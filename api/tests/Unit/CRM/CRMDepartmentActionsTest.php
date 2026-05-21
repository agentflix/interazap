<?php

declare(strict_types=1);

use Domain\CRM\Actions\CRMDepartmentActions;
use Domain\CRM\DTOs\CRMDepartmentDTO;
use Domain\CRM\Models\CRMDepartment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new CRMDepartmentActions;
});

describe('list', function (): void {
    it('returns paginated departments for tenant', function (): void {
        CRMDepartment::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id, []);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(5);
    });

    it('excludes other tenant departments', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        CRMDepartment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CRMDepartment::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id, []);

        expect($result->total())->toBe(3);
    });

    it('orders departments by name', function (): void {
        CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra Dept',
        ]);

        CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Dept',
        ]);

        $result = $this->actions->list($this->tenant->id, []);

        expect($result->first()->name)->toBe('Alpha Dept');
    });

    it('filters departments by search on name and description', function (): void {
        CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finance Squad',
            'description' => 'Billing and invoices',
        ]);

        CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Team',
            'description' => 'Customer care',
        ]);

        $result = $this->actions->list($this->tenant->id, ['search' => 'finance']);

        expect($result->total())->toBe(1)
            ->and($result->first()->name)->toBe('Finance Squad');
    });

    it('filters departments by active status', function (): void {
        CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Dept',
            'is_active' => true,
        ]);

        CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Dept',
            'is_active' => false,
        ]);

        $result = $this->actions->list($this->tenant->id, ['is_active' => false]);

        expect($result->total())->toBe(1)
            ->and($result->first()->name)->toBe('Inactive Dept');
    });
});

describe('all', function (): void {
    it('returns all departments for tenant without pagination', function (): void {
        CRMDepartment::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->all($this->tenant->id);

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($result)->toHaveCount(5);
    });
});

describe('create', function (): void {
    it('creates a new department', function (): void {
        $dto = new CRMDepartmentDTO(
            name: 'Sales Department',
            description: 'Handles sales operations',
            isActive: true,
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(CRMDepartment::class)
            ->and($result->name)->toBe('Sales Department')
            ->and($result->description)->toBe('Handles sales operations')
            ->and($result->is_active)->toBeTrue()
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });

    it('throws exception for duplicate department name', function (): void {
        $dto = new CRMDepartmentDTO(
            name: 'Duplicate Dept',
            description: null,
            isActive: true,
        );

        $this->actions->create($this->tenant->id, $dto);

        expect(fn () => $this->actions->create($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('allows same name for different tenants', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        $dto = new CRMDepartmentDTO(
            name: 'Shared Dept Name',
            description: null,
            isActive: true,
        );

        $this->actions->create($this->tenant->id, $dto);
        $result = $this->actions->create($otherTenant->id, $dto);

        expect($result->name)->toBe('Shared Dept Name')
            ->and($result->tenant_id)->toBe($otherTenant->id);
    });
});

describe('update', function (): void {
    it('updates a department', function (): void {
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);

        $dto = new CRMDepartmentDTO(
            name: 'New Name',
            description: 'Updated description',
            isActive: false,
        );

        $result = $this->actions->update($this->tenant->id, $department->id, $dto);

        expect($result->name)->toBe('New Name')
            ->and($result->description)->toBe('Updated description')
            ->and($result->is_active)->toBeFalse();
    });

    it('throws exception when updating to duplicate name', function (): void {
        $existing = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existing Name',
        ]);

        $department = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other Name',
        ]);

        $dto = new CRMDepartmentDTO(
            name: 'Existing Name',
            description: null,
            isActive: true,
        );

        expect(fn () => $this->actions->update($this->tenant->id, $department->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('allows updating without changing name', function (): void {
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Same Name',
            'description' => 'Old description',
        ]);

        $dto = new CRMDepartmentDTO(
            name: 'Same Name',
            description: 'New description',
            isActive: true,
        );

        $result = $this->actions->update($this->tenant->id, $department->id, $dto);

        expect($result->name)->toBe('Same Name')
            ->and($result->description)->toBe('New description');
    });
});

describe('delete', function (): void {
    it('deletes a department', function (): void {
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->delete($this->tenant->id, $department->id);

        expect(CRMDepartment::query()->find($department->id))->toBeNull();
    });

    it('throws exception for other tenant department', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->delete($this->tenant->id, $department->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('toggleActive', function (): void {
    it('toggles active status from true to false', function (): void {
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $result = $this->actions->toggleActive($this->tenant->id, $department->id);

        expect($result->is_active)->toBeFalse();
    });

    it('toggles active status from false to true', function (): void {
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $result = $this->actions->toggleActive($this->tenant->id, $department->id);

        expect($result->is_active)->toBeTrue();
    });
});

describe('find', function (): void {
    it('finds department by id', function (): void {
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->find($this->tenant->id, $department->id);

        expect($result->id)->toBe($department->id);
    });

    it('throws exception for other tenant department', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $department = CRMDepartment::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->find($this->tenant->id, $department->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
