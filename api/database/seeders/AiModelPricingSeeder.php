<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiModelPricing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder for AI model pricing data.
 *
 * Prices are in USD per 1M tokens (converted from per 1K rates).
 * Source: OpenAI pricing page (as of Jan 2026)
 */
class AiModelPricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            // GPT-4o (flagship model)
            [
                'model_name' => 'gpt-4o',
                'provider' => 'openai',
                'display_name' => 'GPT-4o',
                'input_cost_per_1m' => 2.5,  // $0.0025 per 1K = $2.50 per 1M
                'output_cost_per_1m' => 10.0, // $0.01 per 1K = $10.00 per 1M
                'max_context_tokens' => 128000,
                'is_active' => true,
                'notes' => 'Most capable GPT-4 model with vision capabilities. Best for: complex reasoning, multimodal, coding',
            ],
            // GPT-4o-mini (cost-effective)
            [
                'model_name' => 'gpt-4o-mini',
                'provider' => 'openai',
                'display_name' => 'GPT-4o Mini',
                'input_cost_per_1m' => 0.15,  // $0.00015 per 1K = $0.15 per 1M
                'output_cost_per_1m' => 0.6,  // $0.0006 per 1K = $0.60 per 1M
                'max_context_tokens' => 128000,
                'is_active' => true,
                'notes' => 'Fast and affordable for focused tasks. Best for: classification, summarization, simple tasks',
            ],
            // Text Embedding 3 Small
            [
                'model_name' => 'text-embedding-3-small',
                'provider' => 'openai',
                'display_name' => 'Text Embedding 3 Small',
                'input_cost_per_1m' => 0.02,  // $0.00002 per 1K = $0.02 per 1M
                'output_cost_per_1m' => 0.0,  // embeddings don't have output
                'max_context_tokens' => 8191,
                'is_active' => true,
                'notes' => 'Efficient embedding model (1536 dimensions). Best for: search, clustering, recommendations',
            ],
            // Text Embedding 3 Large
            [
                'model_name' => 'text-embedding-3-large',
                'provider' => 'openai',
                'display_name' => 'Text Embedding 3 Large',
                'input_cost_per_1m' => 0.13,  // $0.00013 per 1K = $0.13 per 1M
                'output_cost_per_1m' => 0.0,
                'max_context_tokens' => 8191,
                'is_active' => true,
                'notes' => 'Higher dimension embeddings (3072) for better accuracy. Best for: high-stakes search, semantic analysis',
            ],
            // GPT-4-turbo (legacy but still used)
            [
                'model_name' => 'gpt-4-turbo',
                'provider' => 'openai',
                'display_name' => 'GPT-4 Turbo',
                'input_cost_per_1m' => 10.0,  // $0.01 per 1K = $10.00 per 1M
                'output_cost_per_1m' => 30.0, // $0.03 per 1K = $30.00 per 1M
                'max_context_tokens' => 128000,
                'is_active' => true,
                'notes' => 'Previous generation flagship model. Best for: complex tasks, legacy compatibility',
            ],
            // GPT-3.5-turbo (budget option)
            [
                'model_name' => 'gpt-3.5-turbo',
                'provider' => 'openai',
                'display_name' => 'GPT-3.5 Turbo',
                'input_cost_per_1m' => 0.5,   // $0.0005 per 1K = $0.50 per 1M
                'output_cost_per_1m' => 1.5,  // $0.0015 per 1K = $1.50 per 1M
                'max_context_tokens' => 16385,
                'is_active' => true,
                'notes' => 'Legacy model for simple tasks. Best for: simple chat, basic completion',
            ],
            // Claude Sonnet (balanced)
            [
                'model_name' => 'claude-3-5-sonnet',
                'provider' => 'anthropic',
                'display_name' => 'Claude 3.5 Sonnet',
                'input_cost_per_1m' => 3.0,
                'output_cost_per_1m' => 15.0,
                'max_context_tokens' => 200000,
                'is_active' => true,
                'notes' => 'Balanced model for reasoning and writing quality.',
            ],
            // Claude Haiku (cost-efficient)
            [
                'model_name' => 'claude-3-haiku',
                'provider' => 'anthropic',
                'display_name' => 'Claude 3 Haiku',
                'input_cost_per_1m' => 0.25,
                'output_cost_per_1m' => 1.25,
                'max_context_tokens' => 200000,
                'is_active' => true,
                'notes' => 'Fast model for lightweight workloads and classification.',
            ],
            // Gemini 1.5 Pro
            [
                'model_name' => 'gemini-1.5-pro',
                'provider' => 'google',
                'display_name' => 'Gemini 1.5 Pro',
                'input_cost_per_1m' => 1.25,
                'output_cost_per_1m' => 5.0,
                'max_context_tokens' => 1000000,
                'is_active' => true,
                'notes' => 'Large-context model suitable for long-context tasks.',
            ],
            // Gemini 2.5 Pro (advanced reasoning)
            [
                'model_name' => 'gemini-2.5-pro',
                'provider' => 'google',
                'display_name' => 'Gemini 2.5 Pro (Avançado)',
                'input_cost_per_1m' => 1.25,
                'output_cost_per_1m' => 10.0,
                'max_context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'is_active' => true,
                'notes' => 'Modelo avançado com raciocínio aprimorado e contexto de 1M tokens.',
            ],
            // Gemini 2.5 Flash (fast & affordable)
            [
                'model_name' => 'gemini-2.5-flash',
                'provider' => 'google',
                'display_name' => 'Gemini 2.5 Flash (Rápido)',
                'input_cost_per_1m' => 0.30,
                'output_cost_per_1m' => 2.5,
                'max_context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'is_active' => true,
                'notes' => 'Modelo rápido e econômico com contexto de 1M tokens.',
            ],
            // Gemini 3.1 Pro Preview (next-gen advanced)
            [
                'model_name' => 'gemini-3.1-pro-preview',
                'provider' => 'google',
                'display_name' => 'Gemini 3.1 Pro (Avançado)',
                'input_cost_per_1m' => 2.0,
                'output_cost_per_1m' => 12.0,
                'max_context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'is_active' => true,
                'notes' => 'Modelo preview; ID pode mudar quando versão estável for lançada.',
            ],
            // Gemini 3.1 Flash Lite Preview (next-gen fast)
            [
                'model_name' => 'gemini-3.1-flash-lite-preview',
                'provider' => 'google',
                'display_name' => 'Gemini 3.1 Flash Lite (Rápido)',
                'input_cost_per_1m' => 0.25,
                'output_cost_per_1m' => 1.5,
                'max_context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'is_active' => true,
                'notes' => 'Modelo preview; ID pode mudar quando versão estável for lançada.',
            ],
            // MiniMax M2.5 (flagship)
            [
                'model_name' => 'MiniMax-M2.5',
                'provider' => 'minimax',
                'display_name' => 'MiniMax M2.5 (Avançado)',
                'input_cost_per_1m' => 10.0,
                'output_cost_per_1m' => 40.0,
                'max_context_tokens' => 204800,
                'max_output_tokens' => 204800,
                'is_active' => true,
                'notes' => 'Modelo flagship MiniMax com desempenho de ponta. ~60 tps.',
            ],
            // MiniMax M2.5 Highspeed (fast)
            [
                'model_name' => 'MiniMax-M2.5-highspeed',
                'provider' => 'minimax',
                'display_name' => 'MiniMax M2.5 Highspeed (Rápido)',
                'input_cost_per_1m' => 10.0,
                'output_cost_per_1m' => 40.0,
                'max_context_tokens' => 204800,
                'max_output_tokens' => 204800,
                'is_active' => true,
                'notes' => 'Versão highspeed do M2.5. ~100 tps.',
            ],
        ];

        foreach ($models as $model) {
            $existing = AiModelPricing::query()
                ->where('model_name', $model['model_name'])
                ->where('provider', $model['provider'])
                ->first();

            if ($existing) {
                $existing->update([
                    'display_name' => $model['display_name'],
                    'input_cost_per_1m' => $model['input_cost_per_1m'],
                    'output_cost_per_1m' => $model['output_cost_per_1m'],
                    'max_context_tokens' => $model['max_context_tokens'],
                    'max_output_tokens' => $model['max_output_tokens'] ?? null,
                    'is_active' => $model['is_active'],
                    'pricing_effective_date' => null,
                    'notes' => $model['notes'],
                ]);
            } else {
                AiModelPricing::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'model_name' => $model['model_name'],
                    'provider' => $model['provider'],
                    'display_name' => $model['display_name'],
                    'input_cost_per_1m' => $model['input_cost_per_1m'],
                    'output_cost_per_1m' => $model['output_cost_per_1m'],
                    'max_context_tokens' => $model['max_context_tokens'],
                    'max_output_tokens' => $model['max_output_tokens'] ?? null,
                    'is_active' => $model['is_active'],
                    'pricing_effective_date' => null,
                    'notes' => $model['notes'],
                ]);
            }
        }

        $this->command->info('AI Model Pricing seeded: '.count($models).' models');
    }
}
