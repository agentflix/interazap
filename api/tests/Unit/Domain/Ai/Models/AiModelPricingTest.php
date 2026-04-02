<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Models;

use Domain\Ai\Models\AiModelPricing;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group pricing
 */
class AiModelPricingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_can_be_created_with_factory(): void
    {
        $pricing = AiModelPricing::factory()->create();

        expect($pricing)->toBeInstanceOf(AiModelPricing::class);
        expect($pricing->id)->toBeString();
        expect($pricing->model_name)->toBeString();
    }

    public function test_it_has_correct_table_name(): void
    {
        $pricing = new AiModelPricing;
        expect($pricing->getTable())->toBe('ai_model_pricings');
    }

    public function test_it_calculates_cost_for_input_tokens(): void
    {
        $pricing = AiModelPricing::factory()->create([
            'input_cost_per_1m' => 3.00, // $3 per million tokens
        ]);

        // 1000 tokens = 1000/1M * $3 = $0.003
        $cost = $pricing->calculateInputCost(1000);

        expect($cost)->toBe(0.003);
    }

    public function test_it_calculates_cost_for_output_tokens(): void
    {
        $pricing = AiModelPricing::factory()->create([
            'output_cost_per_1m' => 15.00, // $15 per million tokens
        ]);

        // 1000 tokens = 1000/1M * $15 = $0.015
        $cost = $pricing->calculateOutputCost(1000);

        expect($cost)->toBe(0.015);
    }

    public function test_it_calculates_total_cost(): void
    {
        $pricing = AiModelPricing::factory()->create([
            'input_cost_per_1m' => 3.00,
            'output_cost_per_1m' => 15.00,
        ]);

        // 1000 input + 500 output = $0.003 + $0.0075 = $0.0105
        $cost = $pricing->calculateTotalCost(1000, 500);

        expect($cost)->toBeGreaterThan(0.0104);
        expect($cost)->toBeLessThan(0.0106);
    }

    public function test_it_scopes_active_models(): void
    {
        AiModelPricing::factory()->create(['is_active' => true]);
        AiModelPricing::factory()->create(['is_active' => false]);

        $active = AiModelPricing::active()->get();

        expect($active)->toHaveCount(1);
    }

    public function test_it_finds_by_model_name(): void
    {
        AiModelPricing::factory()->create(['model_name' => 'gpt-4o']);
        AiModelPricing::factory()->create(['model_name' => 'gpt-4o-mini']);

        $pricing = AiModelPricing::findByModel('gpt-4o');

        expect($pricing)->not->toBeNull();
        expect($pricing->model_name)->toBe('gpt-4o');
    }

    public function test_it_returns_null_for_unknown_model(): void
    {
        $pricing = AiModelPricing::findByModel('unknown-model');

        expect($pricing)->toBeNull();
    }

    public function test_it_has_provider_field(): void
    {
        $pricing = AiModelPricing::factory()->create([
            'provider' => 'openai',
        ]);

        expect($pricing->provider)->toBe('openai');
    }

    public function test_it_scopes_by_provider(): void
    {
        AiModelPricing::factory()->create(['provider' => 'openai']);
        AiModelPricing::factory()->create(['provider' => 'anthropic']);
        AiModelPricing::factory()->create(['provider' => 'openai']);

        $openai = AiModelPricing::byProvider('openai')->get();

        expect($openai)->toHaveCount(2);
    }
}
