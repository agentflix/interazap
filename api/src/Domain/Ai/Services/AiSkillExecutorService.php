<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiAgentSkill;
use Illuminate\Support\Str;

/**
 * Serviço de execução das skills ativas vinculadas a um agente de IA.
 *
 * Contexto: as skills são executadas em duas fases: pré-run (antes da chamada LLM)
 * para enrichment/routing do contexto, e pós-run (após a resposta LLM) para
 * validação, sumarização e extração de dados. Skills são ordenadas por prioridade
 * (metadata.priority, default 100, menor = primeiro).
 */
final class AiSkillExecutorService
{
    /**
     * Executa as skills na fase pré-run (antes da chamada LLM).
     *
     * Aplica skills dos tipos classification, routing e custom para enriquecer
     * o contexto antes da execução do agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  array<string, mixed>  $context  Contexto atual da run.
     * @return array{context: array<string, mixed>, trace: array<int, array<string, mixed>>}
     *                                                                                       Contexto enriquecido e trace de skills aplicadas.
     */
    public function executePreRun(string $tenantId, string $agentId, array $context): array
    {
        $skills = $this->loadSkills($tenantId, $agentId);
        $trace = [];

        foreach ($skills as $skill) {
            $type = $this->resolveType($skill);
            $metadata = is_array($skill->metadata) ? $skill->metadata : [];

            switch ($type) {
                case 'classification':
                    $context['skill_classification'][$skill->name] = [
                        'status' => 'applied',
                        'hint' => (string) ($metadata['hint'] ?? ''),
                    ];
                    break;

                case 'routing':
                    if (is_string($metadata['route_to_agent_id'] ?? null) && $metadata['route_to_agent_id'] !== '') {
                        $context['routed_agent_id'] = $metadata['route_to_agent_id'];
                    }
                    break;

                case 'custom':
                    if (is_string($metadata['prompt_append'] ?? null) && $metadata['prompt_append'] !== '') {
                        $context['skill_prompt_append'][] = $metadata['prompt_append'];
                    }
                    break;
            }

            $trace[] = [
                'skill_id' => (string) $skill->id,
                'name' => (string) $skill->name,
                'type' => $type,
                'phase' => 'pre',
                'applied_at' => now()->toIso8601String(),
            ];
        }

        return [
            'context' => $context,
            'trace' => $trace,
        ];
    }

    /**
     * Executa as skills na fase pós-run (após a resposta LLM).
     *
     * Aplica skills dos tipos validation, summarization e extraction sobre
     * o output gerado pelo agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  array<string, mixed>  $output  Output da execução LLM.
     * @return array{output: array<string, mixed>, trace: array<int, array<string, mixed>>}
     *                                                                                      Output enriquecido e trace de skills aplicadas.
     */
    public function executePostRun(string $tenantId, string $agentId, array $output): array
    {
        $skills = $this->loadSkills($tenantId, $agentId);
        $trace = [];

        foreach ($skills as $skill) {
            $type = $this->resolveType($skill);
            $metadata = is_array($skill->metadata) ? $skill->metadata : [];

            switch ($type) {
                case 'validation':
                    $requiredKeywords = is_array($metadata['required_keywords'] ?? null)
                        ? $metadata['required_keywords']
                        : [];

                    $response = Str::lower((string) ($output['response'] ?? ''));
                    $missingKeywords = array_values(array_filter(
                        $requiredKeywords,
                        static fn (mixed $keyword): bool => is_string($keyword) && ! str_contains($response, Str::lower($keyword)),
                    ));

                    if ($missingKeywords !== []) {
                        $output['skill_validation'] = [
                            'valid' => false,
                            'missing_keywords' => $missingKeywords,
                        ];
                    }
                    break;

                case 'summarization':
                    $output['skill_summary'] = Str::limit((string) ($output['response'] ?? ''), 240);
                    break;

                case 'extraction':
                    $fields = is_array($metadata['extract_fields'] ?? null)
                        ? array_values(array_filter($metadata['extract_fields'], static fn (mixed $field): bool => is_string($field) && $field !== ''))
                        : [];
                    if ($fields !== []) {
                        $output['skill_extraction'] = [
                            'fields' => $fields,
                            'status' => 'pending_manual_mapping',
                        ];
                    }
                    break;
            }

            $trace[] = [
                'skill_id' => (string) $skill->id,
                'name' => (string) $skill->name,
                'type' => $type,
                'phase' => 'post',
                'applied_at' => now()->toIso8601String(),
            ];
        }

        return [
            'output' => $output,
            'trace' => $trace,
        ];
    }

    /**
     * Carrega e ordena as skills ativas do agente por prioridade.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @return list<AiAgentSkill> Skills ordenadas por metadata.priority (asc).
     */
    private function loadSkills(string $tenantId, string $agentId): array
    {
        $skills = AiAgentSkill::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->get();

        return $skills
            ->sortBy(static function (AiAgentSkill $skill): int {
                $metadata = is_array($skill->metadata) ? $skill->metadata : [];
                $priority = $metadata['priority'] ?? 100;

                return is_numeric($priority) ? (int) $priority : 100;
            })
            ->values()
            ->all();
    }

    /**
     * Resolve o tipo da skill a partir de metadata.type com fallback para 'custom'.
     *
     * @param  AiAgentSkill  $skill  Skill a processar.
     * @return string Tipo normalizado (classification, extraction, routing, validation, summarization, custom).
     */
    private function resolveType(AiAgentSkill $skill): string
    {
        $metadata = is_array($skill->metadata) ? $skill->metadata : [];
        $type = (string) ($metadata['type'] ?? 'custom');

        return match ($type) {
            'classification', 'extraction', 'routing', 'validation', 'summarization', 'custom' => $type,
            default => 'custom',
        };
    }
}
