<?php

declare(strict_types=1);

use Domain\CRM\Actions\CRMCustomFieldValueActions;
use Domain\CRM\DTOs\CRMCustomFieldValueDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMCustomFieldValue;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new CRMCustomFieldValueActions;
});

describe('upsert', function (): void {
    it('creates a new custom field value', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'text',
            'entity' => 'contact',
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'Test Value',
        ], CRMContact::class, $contact->id);

        $result = $this->actions->upsert($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(CRMCustomFieldValue::class)
            ->and($result->value)->toBe('Test Value')
            ->and($result->entity_id)->toBe($contact->id)
            ->and($result->crm_custom_field_id)->toBe($field->id);
    });

    it('updates existing custom field value', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'text',
            'entity' => 'contact',
        ]);

        $dto1 = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'Initial Value',
        ], CRMContact::class, $contact->id);

        $this->actions->upsert($this->tenant->id, $dto1);

        $dto2 = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'Updated Value',
        ], CRMContact::class, $contact->id);

        $result = $this->actions->upsert($this->tenant->id, $dto2);

        expect($result->value)->toBe('Updated Value');

        $count = CRMCustomFieldValue::query()
            ->where('entity_id', $contact->id)
            ->where('crm_custom_field_id', $field->id)
            ->count();

        expect($count)->toBe(1);
    });

    it('validates number type', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'number',
            'entity' => 'contact',
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'not-a-number',
        ], CRMContact::class, $contact->id);

        expect(fn () => $this->actions->upsert($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('accepts valid number', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'number',
            'entity' => 'contact',
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => '42.5',
        ], CRMContact::class, $contact->id);

        $result = $this->actions->upsert($this->tenant->id, $dto);

        expect($result->value)->toBe('42.5');
    });

    it('validates date type', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'date',
            'entity' => 'contact',
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'invalid-date',
        ], CRMContact::class, $contact->id);

        expect(fn () => $this->actions->upsert($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('accepts valid date', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'date',
            'entity' => 'contact',
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => '2024-12-25',
        ], CRMContact::class, $contact->id);

        $result = $this->actions->upsert($this->tenant->id, $dto);

        expect($result->value)->toBe('2024-12-25');
    });

    it('validates select type options', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'select',
            'entity' => 'contact',
            'options' => ['option1', 'option2', 'option3'],
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'invalid-option',
        ], CRMContact::class, $contact->id);

        expect(fn () => $this->actions->upsert($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('accepts valid select option', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'select',
            'entity' => 'contact',
            'options' => ['option1', 'option2', 'option3'],
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'option2',
        ], CRMContact::class, $contact->id);

        $result = $this->actions->upsert($this->tenant->id, $dto);

        expect($result->value)->toBe('option2');
    });

    it('validates multiselect type options', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'multiselect',
            'entity' => 'contact',
            'options' => ['opt1', 'opt2', 'opt3'],
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => ['opt1', 'invalid'],
        ], CRMContact::class, $contact->id);

        expect(fn () => $this->actions->upsert($this->tenant->id, $dto))
            ->toThrow(ValidationException::class);
    });

    it('accepts valid multiselect options', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'multiselect',
            'entity' => 'contact',
            'options' => ['opt1', 'opt2', 'opt3'],
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => ['opt1', 'opt3'],
        ], CRMContact::class, $contact->id);

        $result = $this->actions->upsert($this->tenant->id, $dto);

        expect(json_decode((string) $result->value, true))->toBe(['opt1', 'opt3']);
    });

    it('throws when custom field does not exist', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => '00000000-0000-0000-0000-000000000000',
            'value' => 'Test',
        ], CRMContact::class, $contact->id);

        expect(fn () => $this->actions->upsert($this->tenant->id, $dto))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    it('isolates custom field values by tenant', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $otherTenant->id,
            'type' => 'text',
            'entity' => 'contact',
        ]);

        $dto = CRMCustomFieldValueDTO::fromArray([
            'crm_custom_field_id' => $field->id,
            'value' => 'Test',
        ], CRMContact::class, $contact->id);

        expect(fn () => $this->actions->upsert($this->tenant->id, $dto))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
