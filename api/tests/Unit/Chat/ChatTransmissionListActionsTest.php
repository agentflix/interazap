<?php

declare(strict_types=1);

use Domain\Chat\Actions\ChatTransmissionListActions;
use Domain\Chat\DTOs\ChatTransmissionListDTO;
use Domain\Chat\Jobs\ProcessTransmissionListJob;
use Domain\Chat\Models\ChatTransmissionList;
use Domain\Chat\Models\ChatTransmissionListContact;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new ChatTransmissionListActions;
});

describe('list', function (): void {
    it('returns paginated transmission lists for tenant', function (): void {
        ChatTransmissionList::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(5);
    });

    it('excludes other tenant transmission lists', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        ChatTransmissionList::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        ChatTransmissionList::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->total())->toBe(3);
    });
});

describe('create', function (): void {
    it('creates a new transmission list', function (): void {
        $dto = new ChatTransmissionListDTO(
            name: 'Black Friday',
            status: 'draft',
            message: 'Check out our deals!',
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(ChatTransmissionList::class)
            ->and($result->name)->toBe('Black Friday')
            ->and($result->message)->toBe('Check out our deals!')
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });
});

describe('update', function (): void {
    it('updates an existing transmission list', function (): void {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old List',
        ]);

        $dto = new ChatTransmissionListDTO(
            name: 'Updated List',
            status: 'draft',
            message: 'New message',
        );

        $result = $this->actions->update($this->tenant->id, $transmissionList->id, $dto);

        expect($result->name)->toBe('Updated List')
            ->and($result->message)->toBe('New message');
    });
});

describe('delete', function (): void {
    it('deletes a transmission list', function (): void {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->delete($this->tenant->id, $transmissionList->id);

        expect(ChatTransmissionList::query()->find($transmissionList->id))->toBeNull();
    });
});

describe('find', function (): void {
    it('finds a transmission list by id', function (): void {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->find($this->tenant->id, $transmissionList->id);

        expect($result->id)->toBe($transmissionList->id);
    });

    it('throws exception for other tenant transmission list', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->find($this->tenant->id, $transmissionList->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('resolveContacts', function (): void {
    it('returns all contacts for tenant without criteria', function (): void {
        CRMContact::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->resolveContacts($this->tenant->id, []);

        expect($result)->toHaveCount(5);
    });

    it('filters by active status', function (): void {
        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        CRMContact::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $result = $this->actions->resolveContacts($this->tenant->id, ['status' => 'active']);

        expect($result)->toHaveCount(3);
    });

    it('filters by inactive status', function (): void {
        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        CRMContact::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $result = $this->actions->resolveContacts($this->tenant->id, ['status' => 'inactive']);

        expect($result)->toHaveCount(2);
    });

    it('ignores all status filter', function (): void {
        CRMContact::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->resolveContacts($this->tenant->id, ['status' => 'all']);

        expect($result)->toHaveCount(5);
    });

    it('excludes other tenant contacts', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CRMContact::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->resolveContacts($this->tenant->id, []);

        expect($result)->toHaveCount(3);
    });
});

describe('countAudience', function (): void {
    it('counts contacts matching criteria', function (): void {
        CRMContact::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $result = $this->actions->countAudience($this->tenant->id, ['status' => 'active']);

        expect($result)->toBe(5);
    });

    it('counts all contacts without criteria', function (): void {
        CRMContact::factory()->count(8)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->countAudience($this->tenant->id, []);

        expect($result)->toBe(8);
    });
});

describe('send', function (): void {
    it('sends transmission list and dispatches job', function (): void {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->send($this->tenant->id, $transmissionList->id);

        expect($result->status)->toBe('running');
        expect(ChatTransmissionListContact::query()->where('transmission_list_id', $transmissionList->id)->count())->toBe(3);

        Queue::assertPushed(ProcessTransmissionListJob::class);
    });

    it('updates filter criteria when provided', function (): void {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'filter_criteria' => [],
        ]);

        $criteria = ['status' => 'active'];
        $result = $this->actions->send($this->tenant->id, $transmissionList->id, $criteria);

        expect($result->filter_criteria)->toBe($criteria);
    });

    it('rejects sending when transmission list is already running', function (): void {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
        ]);

        expect(fn () => $this->actions->send($this->tenant->id, $transmissionList->id))
            ->toThrow(ValidationException::class);
    });
});

describe('preview', function (): void {
    it('generates preview replacing variables for sample contact', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ana Example',
            'phone' => '+5511999887766',
        ]);

        $result = $this->actions->preview($this->tenant->id, 'Olá {{name}} - {{phone}}');

        expect($result['preview'])->toBe('Olá '.$contact->name.' - '.$contact->phone)
            ->and($result['vars_detected'])->toContain('{{name}}', '{{phone}}')
            ->and($result['sample_contact']['name'])->toBe($contact->name);
    });
});
