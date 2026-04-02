<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds Gemini 2.5 and 3.1 models to ai_model_pricings.
 *
 * Additive migration — does not alter the schema.
 * Uses upsert to safely handle re-runs.
 */
return new class extends Migration
{
    /** @var list<array{provider: string, model_name: string, display_name: string, input_cost_per_1m: float, output_cost_per_1m: float, max_context_tokens: int, max_output_tokens: int, is_active: bool, pricing_effective_date: string, notes: string}> */
    private array $models = [
        [
            'provider' => 'google',
            'model_name' => 'gemini-2.5-pro',
            'display_name' => 'Gemini 2.5 Pro (Avançado)',
            'input_cost_per_1m' => 1.25,
            'output_cost_per_1m' => 10.00,
            'max_context_tokens' => 1048576,
            'max_output_tokens' => 65536,
            'is_active' => true,
            'pricing_effective_date' => '2026-03-31',
            'notes' => 'Modelo avançado com raciocínio aprimorado e contexto de 1M tokens.',
        ],
        [
            'provider' => 'google',
            'model_name' => 'gemini-2.5-flash',
            'display_name' => 'Gemini 2.5 Flash (Rápido)',
            'input_cost_per_1m' => 0.30,
            'output_cost_per_1m' => 2.50,
            'max_context_tokens' => 1048576,
            'max_output_tokens' => 65536,
            'is_active' => true,
            'pricing_effective_date' => '2026-03-31',
            'notes' => 'Modelo rápido e econômico com contexto de 1M tokens.',
        ],
        [
            'provider' => 'google',
            'model_name' => 'gemini-3.1-pro-preview',
            'display_name' => 'Gemini 3.1 Pro (Avançado)',
            'input_cost_per_1m' => 2.00,
            'output_cost_per_1m' => 12.00,
            'max_context_tokens' => 1048576,
            'max_output_tokens' => 65536,
            'is_active' => true,
            'pricing_effective_date' => '2026-03-31',
            'notes' => 'Modelo preview; ID pode mudar quando versão estável for lançada.',
        ],
        [
            'provider' => 'google',
            'model_name' => 'gemini-3.1-flash-lite-preview',
            'display_name' => 'Gemini 3.1 Flash Lite (Rápido)',
            'input_cost_per_1m' => 0.25,
            'output_cost_per_1m' => 1.50,
            'max_context_tokens' => 1048576,
            'max_output_tokens' => 65536,
            'is_active' => true,
            'pricing_effective_date' => '2026-03-31',
            'notes' => 'Modelo preview; ID pode mudar quando versão estável for lançada.',
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
            ->where('provider', 'google')
            ->whereIn('model_name', $modelNames)
            ->delete();
    }
};
