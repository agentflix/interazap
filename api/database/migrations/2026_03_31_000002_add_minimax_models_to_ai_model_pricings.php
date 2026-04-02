<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds MiniMax models to ai_model_pricings.
 *
 * Additive migration — does not alter the schema.
 * Uses upsert to safely handle re-runs.
 */
return new class extends Migration
{
    /** @var list<array{provider: string, model_name: string, display_name: string, input_cost_per_1m: float, output_cost_per_1m: float, max_context_tokens: int, max_output_tokens: int, is_active: bool, pricing_effective_date: string, notes: string}> */
    private array $models = [
        [
            'provider' => 'minimax',
            'model_name' => 'MiniMax-M2.5',
            'display_name' => 'MiniMax M2.5 (Avançado)',
            'input_cost_per_1m' => 10.0,
            'output_cost_per_1m' => 40.0,
            'max_context_tokens' => 204800,
            'max_output_tokens' => 204800,
            'is_active' => true,
            'pricing_effective_date' => '2026-03-31',
            'notes' => 'Modelo flagship MiniMax com desempenho de ponta. ~60 tps.',
        ],
        [
            'provider' => 'minimax',
            'model_name' => 'MiniMax-M2.5-highspeed',
            'display_name' => 'MiniMax M2.5 Highspeed (Rápido)',
            'input_cost_per_1m' => 10.0,
            'output_cost_per_1m' => 40.0,
            'max_context_tokens' => 204800,
            'max_output_tokens' => 204800,
            'is_active' => true,
            'pricing_effective_date' => '2026-03-31',
            'notes' => 'Versão highspeed do M2.5. ~100 tps.',
        ],
    ];

    public function up(): void
    {
        $now = now();

        $rows = array_map(fn (array $model): array => [
            'id' => Str::uuid()->toString(),
            'provider' => $model['provider'],
            'model_name' => $model['model_name'],
            'display_name' => $model['display_name'],
            'input_cost_per_1m' => $model['input_cost_per_1m'],
            'output_cost_per_1m' => $model['output_cost_per_1m'],
            'max_context_tokens' => $model['max_context_tokens'],
            'max_output_tokens' => $model['max_output_tokens'],
            'is_active' => $model['is_active'],
            'pricing_effective_date' => $model['pricing_effective_date'],
            'notes' => $model['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->models);

        DB::table('ai_model_pricings')->upsert(
            $rows,
            ['provider', 'model_name'],
            [
                'display_name',
                'input_cost_per_1m',
                'output_cost_per_1m',
                'max_context_tokens',
                'max_output_tokens',
                'is_active',
                'pricing_effective_date',
                'notes',
                'updated_at',
            ],
        );
    }

    public function down(): void
    {
        $modelNames = array_column($this->models, 'model_name');

        DB::table('ai_model_pricings')
            ->where('provider', 'minimax')
            ->whereIn('model_name', $modelNames)
            ->delete();
    }
};
