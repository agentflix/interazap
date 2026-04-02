<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Actions;

use Domain\Platform\Actions\PlatformPlanQueryActions;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformPlanQueryActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformPlanQueryActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformPlan::query()->delete();
        $this->actions = new PlatformPlanQueryActions;
    }

    public function test_list_returns_paginated_plans(): void
    {
        PlatformPlan::factory()->count(5)->create();

        $result = $this->actions->list();

        $this->assertCount(5, $result->items());
    }

    public function test_list_filters_by_search_term(): void
    {
        PlatformPlan::factory()->create(['name' => 'Enterprise Plan', 'slug' => 'enterprise']);
        PlatformPlan::factory()->create(['name' => 'Basic Plan', 'slug' => 'basic']);
        PlatformPlan::factory()->create(['name' => 'Pro Plan', 'slug' => 'pro']);

        $result = $this->actions->list('enterprise');

        $this->assertCount(1, $result->items());
        $this->assertSame('Enterprise Plan', $result->items()[0]->name);
    }

    public function test_list_searches_by_slug(): void
    {
        PlatformPlan::factory()->create(['name' => 'Some Plan', 'slug' => 'unique-slug']);
        PlatformPlan::factory()->create(['name' => 'Other Plan', 'slug' => 'other']);

        $result = $this->actions->list('unique');

        $this->assertCount(1, $result->items());
        $this->assertSame('unique-slug', $result->items()[0]->slug);
    }

    public function test_list_orders_by_latest(): void
    {
        PlatformPlan::factory()->create([
            'name' => 'Oldest',
            'created_at' => now()->subDays(3),
        ]);
        PlatformPlan::factory()->create([
            'name' => 'Newest',
            'created_at' => now(),
        ]);
        PlatformPlan::factory()->create([
            'name' => 'Middle',
            'created_at' => now()->subDays(1),
        ]);

        $result = $this->actions->list();

        $this->assertSame('Newest', $result->items()[0]->name);
        $this->assertSame('Middle', $result->items()[1]->name);
        $this->assertSame('Oldest', $result->items()[2]->name);
    }

    public function test_list_respects_per_page(): void
    {
        PlatformPlan::factory()->count(20)->create();

        $result = $this->actions->list('', 5);

        $this->assertCount(5, $result->items());
        $this->assertSame(20, $result->total());
    }

    public function test_find_returns_plan_by_id(): void
    {
        $plan = PlatformPlan::factory()->create(['name' => 'Find Me']);

        $found = $this->actions->find($plan->id);

        $this->assertSame($plan->id, $found->id);
        $this->assertSame('Find Me', $found->name);
    }

    public function test_find_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->actions->find('00000000-0000-0000-0000-000000000000');
    }

    public function test_toggle_activates_inactive_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['is_active' => false]);

        $toggled = $this->actions->toggle($plan->id);

        $this->assertTrue($toggled->is_active);
    }

    public function test_toggle_deactivates_active_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['is_active' => true]);

        $toggled = $this->actions->toggle($plan->id);

        $this->assertFalse($toggled->is_active);
    }

    public function test_validate_slug_returns_true_for_unique_slug(): void
    {
        PlatformPlan::factory()->create(['slug' => 'existing-slug']);

        $isValid = $this->actions->validateSlug('new-unique-slug');

        $this->assertTrue($isValid);
    }

    public function test_validate_slug_returns_false_for_duplicate_slug(): void
    {
        PlatformPlan::factory()->create(['slug' => 'existing-slug']);

        $isValid = $this->actions->validateSlug('existing-slug');

        $this->assertFalse($isValid);
    }

    public function test_validate_slug_excludes_current_plan_id(): void
    {
        $plan = PlatformPlan::factory()->create(['slug' => 'my-slug']);

        // Same slug should be valid when excluding current plan
        $isValid = $this->actions->validateSlug('my-slug', $plan->id);

        $this->assertTrue($isValid);
    }

    public function test_validate_slug_returns_false_when_other_plan_has_slug(): void
    {
        PlatformPlan::factory()->create(['slug' => 'plan-1']);
        $plan2 = PlatformPlan::factory()->create(['slug' => 'plan-2']);

        // plan-1 should not be valid when checking for plan-2
        $isValid = $this->actions->validateSlug('plan-1', $plan2->id);

        $this->assertFalse($isValid);
    }
}
