<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use Domain\Ai\Actions\AiKnowledgeActions;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiKnowledgeActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AiKnowledgeActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new AiKnowledgeActions;
    }

    public function test_list_returns_only_active_documents_for_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        // Create active documents for target tenant
        AiKnowledgeDocument::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        // Create inactive document for target tenant
        AiKnowledgeDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        // Create documents for other tenant
        AiKnowledgeDocument::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        $result = $this->actions->list($tenant->id);

        $this->assertCount(3, $result->items());
        foreach ($result->items() as $doc) {
            $this->assertSame($tenant->id, $doc->tenant_id);
            $this->assertTrue($doc->is_active);
        }
    }

    public function test_list_orders_by_created_at_descending(): void
    {
        $tenant = PlatformTenant::factory()->create();

        AiKnowledgeDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'name' => 'Oldest',
            'created_at' => now()->subDays(3),
        ]);

        AiKnowledgeDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'name' => 'Middle',
            'created_at' => now()->subDays(1),
        ]);

        AiKnowledgeDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'name' => 'Newest',
            'created_at' => now(),
        ]);

        $result = $this->actions->list($tenant->id);

        $items = $result->items();
        $this->assertSame('Newest', $items[0]->name);
        $this->assertSame('Middle', $items[1]->name);
        $this->assertSame('Oldest', $items[2]->name);
    }

    public function test_find_active_returns_document_when_exists_and_active(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $doc = AiKnowledgeDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'name' => 'Active Document',
        ]);

        $found = $this->actions->findActive($tenant->id, $doc->id);

        $this->assertNotNull($found);
        $this->assertSame($doc->id, $found->id);
        $this->assertSame('Active Document', $found->name);
    }

    public function test_find_active_returns_null_when_document_inactive(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $doc = AiKnowledgeDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
            'name' => 'Inactive Document',
        ]);

        $found = $this->actions->findActive($tenant->id, $doc->id);

        $this->assertNull($found);
    }

    public function test_find_active_returns_null_when_document_belongs_to_other_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        $doc = AiKnowledgeDocument::factory()->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
            'name' => 'Other Tenant Document',
        ]);

        $found = $this->actions->findActive($tenant->id, $doc->id);

        $this->assertNull($found);
    }

    public function test_find_active_returns_null_when_document_not_found(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $found = $this->actions->findActive($tenant->id, '00000000-0000-0000-0000-000000000000');

        $this->assertNull($found);
    }

    public function test_list_paginates_with_20_items_per_page(): void
    {
        $tenant = PlatformTenant::factory()->create();

        AiKnowledgeDocument::factory()->count(25)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $result = $this->actions->list($tenant->id);

        $this->assertCount(20, $result->items());
        $this->assertSame(25, $result->total());
        $this->assertSame(2, $result->lastPage());
    }
}
