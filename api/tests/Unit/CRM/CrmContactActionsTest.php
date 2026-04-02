<?php

declare(strict_types=1);

use Domain\CRM\Actions\CRMContactActions;
use Domain\CRM\DTOs\CRMContactDTO;
use Domain\CRM\DTOs\CRMContactPhoneDTO;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new CRMContactActions;
});

describe('list', function (): void {
    it('returns paginated contacts for tenant', function (): void {
        CRMContact::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(5);
    });

    it('excludes other tenant contacts', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CRMContact::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->total())->toBe(3);
    });

    it('orders contacts by name', function (): void {
        CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra',
        ]);

        CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha',
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->first()->name)->toBe('Alpha');
    });
});

describe('create', function (): void {
    it('creates a new contact', function (): void {
        $dto = new CRMContactDTO(
            name: 'John Doe',
            email: 'john@example.com',
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(CRMContact::class)
            ->and($result->name)->toBe('John Doe')
            ->and($result->email)->toBe('john@example.com')
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });

    it('creates contact with phone', function (): void {
        $dto = new CRMContactDTO(
            name: 'Jane Doe',
            email: 'jane@example.com',
            phone_e164: '+5511999999999',
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result->phones)->toHaveCount(1)
            ->and($result->phones->first()->phone_e164)->toBe('+5511999999999');
    });

    it('creates contact with company', function (): void {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $dto = new CRMContactDTO(
            name: 'Employee',
            crm_company_id: $company->id,
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result->crm_company_id)->toBe($company->id)
            ->and($result->company)->not->toBeNull();
    });
});

describe('update', function (): void {
    it('updates an existing contact', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);

        $dto = new CRMContactDTO(
            name: 'New Name',
            email: 'new@example.com',
        );

        $result = $this->actions->update($this->tenant->id, $contact->id, $dto);

        expect($result->name)->toBe('New Name')
            ->and($result->email)->toBe('new@example.com');
    });
});

describe('updatePartial', function (): void {
    it('updates only provided attributes', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'position' => null,
            'document' => null,
        ]);

        $result = $this->actions->updatePartial($this->tenant->id, $contact->id, [
            'position' => 'CTO',
            'document' => '12345678900',
        ]);

        expect($result->position)->toBe('CTO')
            ->and($result->document)->toBe('12345678900');
    });

    it('rejects contacts from other tenants', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->updatePartial($this->tenant->id, $contact->id, ['name' => 'Blocked']))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('delete', function (): void {
    it('deletes a contact', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->delete($this->tenant->id, $contact->id);

        expect(\Domain\CRM\Models\CRMContact::query()->find($contact->id))->toBeNull();
    });
});

describe('restore', function (): void {
    it('restores a soft deleted contact', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $contact->delete();

        $restored = $this->actions->restore($this->tenant->id, $contact->id);

        expect($restored->trashed())->toBeFalse()
            ->and($restored->id)->toBe($contact->id);
    });
});

describe('createPhone', function (): void {
    it('creates a phone for contact', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $dto = new CRMContactPhoneDTO(
            crm_contact_id: $contact->id,
            phone_e164: '+5511888888888',
            label: 'work',
            is_primary: false,
        );

        $result = $this->actions->createPhone($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(CRMContactPhone::class)
            ->and($result->phone_e164)->toBe('+5511888888888')
            ->and($result->label)->toBe('work');
    });

    it('throws exception for duplicate phone', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $dto = new CRMContactPhoneDTO(
            crm_contact_id: $contact->id,
            phone_e164: '+5511777777777',
            label: 'mobile',
            is_primary: true,
        );

        $this->actions->createPhone($this->tenant->id, $dto);

        expect(fn () => $this->actions->createPhone($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('reassigns phone when force flag is true', function (): void {
        $original = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $replacement = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $dto = new CRMContactPhoneDTO(
            crm_contact_id: $original->id,
            phone_e164: '+5511666666666',
            label: 'main',
            is_primary: true,
        );

        $this->actions->createPhone($this->tenant->id, $dto);

        $reassigned = $this->actions->createPhone($this->tenant->id, new CRMContactPhoneDTO(
            crm_contact_id: $replacement->id,
            phone_e164: '+5511666666666',
            label: 'mobile',
            is_primary: false,
        ), true);

        $previous = CRMContactPhone::query()->where('crm_contact_id', $original->id)->first();

        expect($reassigned->crm_contact_id)->toBe($replacement->id)
            ->and($previous?->valid_to)->not->toBeNull();
    });
});

describe('find', function (): void {
    it('finds contact by id', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->find($this->tenant->id, $contact->id);

        expect($result->id)->toBe($contact->id);
    });

    it('throws exception for other tenant contact', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->find($this->tenant->id, $contact->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('findByPhoneWithContext', function (): void {
    it('finds contact by phone field', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+5511999999999',
        ]);

        $result = $this->actions->findByPhoneWithContext($this->tenant->id, '+5511999999999');

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($contact->id);
    });

    it('finds contact by whatsapp field', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '+5511888888888',
        ]);

        $result = $this->actions->findByPhoneWithContext($this->tenant->id, '+5511888888888');

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($contact->id);
    });

    it('finds contact by phone table', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CRMContactPhone::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_contact_id' => $contact->id,
            'phone_e164' => '+5511777777777',
            'valid_to' => null,
        ]);

        $result = $this->actions->findByPhoneWithContext($this->tenant->id, '+5511777777777');

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($contact->id);
    });

    it('returns null for non-existent phone', function (): void {
        $result = $this->actions->findByPhoneWithContext($this->tenant->id, '+5511000000000');

        expect($result)->toBeNull();
    });

    it('loads relationships', function (): void {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+5511999999999',
            'crm_company_id' => $company->id,
        ]);

        $result = $this->actions->findByPhoneWithContext($this->tenant->id, '+5511999999999');

        expect($result->relationLoaded('company'))->toBeTrue()
            ->and($result->relationLoaded('tags'))->toBeTrue()
            ->and($result->relationLoaded('negotiations'))->toBeTrue();
    });
});
