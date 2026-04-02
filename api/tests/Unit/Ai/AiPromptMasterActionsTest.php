<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use Domain\Ai\Actions\Prompts\AiPromptMasterActions;
use Domain\Ai\Models\AiPromptMaster;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiPromptMasterActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AiPromptMasterActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new AiPromptMasterActions;
    }

    public function test_list_returns_all_master_prompts(): void
    {
        AiPromptMaster::factory()->count(3)->create();

        $result = $this->actions->list();

        $this->assertCount(3, $result);
    }

    public function test_list_orders_by_version_descending(): void
    {
        AiPromptMaster::factory()->create(['version' => 1, 'is_active' => false]);
        AiPromptMaster::factory()->create(['version' => 3, 'is_active' => true]);
        AiPromptMaster::factory()->create(['version' => 2, 'is_active' => false]);

        $result = $this->actions->list();

        $this->assertSame(3, $result[0]->version);
        $this->assertSame(2, $result[1]->version);
        $this->assertSame(1, $result[2]->version);
    }

    public function test_deactivate_sets_is_active_to_false(): void
    {
        $master = AiPromptMaster::factory()->create([
            'version' => 1,
            'is_active' => true,
        ]);

        $this->assertTrue($master->is_active);

        $deactivated = $this->actions->deactivate($master);

        $this->assertFalse($deactivated->is_active);
        $this->assertDatabaseHas('ai_prompt_masters', [
            'id' => $master->id,
            'is_active' => false,
        ]);
    }

    public function test_deactivate_on_already_inactive_returns_same_state(): void
    {
        $master = AiPromptMaster::factory()->create([
            'version' => 1,
            'is_active' => false,
        ]);

        $deactivated = $this->actions->deactivate($master);

        $this->assertFalse($deactivated->is_active);
    }

    public function test_list_returns_empty_when_no_prompts(): void
    {
        $result = $this->actions->list();

        $this->assertCount(0, $result);
    }
}
