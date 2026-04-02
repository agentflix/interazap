<?php

declare(strict_types=1);

use Domain\Chat\Actions\ChatCampaignActions;
use Domain\Chat\DTOs\ChatCampaignDTO;
use Domain\Chat\Jobs\ProcessCampaignJob;
use Domain\Chat\Models\ChatCampaign;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new ChatCampaignActions;
});

describe('list', function (): void {
    it('returns paginated campaigns for tenant', function (): void {
        ChatCampaign::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(5);
    });

    it('excludes other tenant campaigns', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        ChatCampaign::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        ChatCampaign::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->total())->toBe(3);
    });
});

describe('create', function (): void {
    it('creates a new campaign', function (): void {
        $dto = new ChatCampaignDTO(
            name: 'Black Friday',
            status: 'draft',
            message: 'Check out our deals!',
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(ChatCampaign::class)
            ->and($result->name)->toBe('Black Friday')
            ->and($result->message)->toBe('Check out our deals!')
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });
});

describe('update', function (): void {
    it('updates an existing campaign', function (): void {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Campaign',
        ]);

        $dto = new ChatCampaignDTO(
            name: 'Updated Campaign',
            status: 'draft',
            message: 'New message',
        );

        $result = $this->actions->update($this->tenant->id, $campaign->id, $dto);

        expect($result->name)->toBe('Updated Campaign')
            ->and($result->message)->toBe('New message');
    });
});

describe('delete', function (): void {
    it('deletes a campaign', function (): void {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->delete($this->tenant->id, $campaign->id);

        expect(\Domain\Chat\Models\ChatCampaign::query()->find($campaign->id))->toBeNull();
    });
});

describe('find', function (): void {
    it('finds a campaign by id', function (): void {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->find($this->tenant->id, $campaign->id);

        expect($result->id)->toBe($campaign->id);
    });

    it('throws exception for other tenant campaign', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->find($this->tenant->id, $campaign->id))
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
    it('sends campaign and dispatches job', function (): void {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->send($this->tenant->id, $campaign->id);

        expect($result->status)->toBe('running');
        expect(\Domain\Chat\Models\ChatCampaignContact::query()->where('campaign_id', $campaign->id)->count())->toBe(3);

        Queue::assertPushed(ProcessCampaignJob::class);
    });

    it('updates filter criteria when provided', function (): void {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'filter_criteria' => [],
        ]);

        $criteria = ['status' => 'active'];
        $result = $this->actions->send($this->tenant->id, $campaign->id, $criteria);

        expect($result->filter_criteria)->toBe($criteria);
    });

    it('rejects sending when campaign is already running', function (): void {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
        ]);

        expect(fn () => $this->actions->send($this->tenant->id, $campaign->id))
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
