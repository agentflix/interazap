<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\DTOs\GuardrailEvaluationResult;
use Domain\Ai\Models\AiAutopilotGuardrail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates active tenant guardrails against runtime actions.
 */
final class GuardrailEvaluatorService
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public function evaluate(
        string $tenantId,
        string $actionType,
        array $input,
        array $output = [],
    ): GuardrailEvaluationResult {
        $guardrails = $this->resolveGuardrails($tenantId);

        $evaluations = [];

        foreach ($guardrails as $guardrail) {
            if (! $this->matchesConditions(
                conditions: (array) data_get($guardrail, 'conditions', []),
                actionType: $actionType,
                input: $input,
                output: $output,
                ruleType: (string) data_get($guardrail, 'rule_type', 'LOG'),
            )) {
                continue;
            }

            $evaluation = [
                'guardrail_id' => (string) data_get($guardrail, 'id', 'static-guardrail'),
                'name' => (string) data_get($guardrail, 'name', 'Static Guardrail'),
                'rule_type' => (string) data_get($guardrail, 'rule_type', 'LOG'),
                'matched' => true,
            ];

            $evaluations[] = $evaluation;

            if (strtoupper((string) data_get($guardrail, 'rule_type', 'LOG')) === 'BLOCK') {
                return GuardrailEvaluationResult::blocked($evaluations);
            }
        }

        return GuardrailEvaluationResult::passed($evaluations);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveGuardrails(string $tenantId): array
    {
        return Cache::remember(
            $this->guardrailsCacheKey($tenantId),
            300,
            fn (): array => $this->mergeGuardrails($tenantId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mergeGuardrails(string $tenantId): array
    {
        $configured = config('ai.autopilot.static_guardrails', []);
        $staticGuardrails = is_array($configured) ? array_values($configured) : [];

        if ($tenantId === '') {
            return $staticGuardrails;
        }

        /** @var Collection<int, AiAutopilotGuardrail> $databaseGuardrails */
        $databaseGuardrails = AiAutopilotGuardrail::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $dbGuardrails = $databaseGuardrails
            ->map(static fn (AiAutopilotGuardrail $guardrail): array => [
                'id' => (string) $guardrail->id,
                'name' => (string) $guardrail->name,
                'rule_type' => (string) $guardrail->rule_type,
                'conditions' => is_array($guardrail->conditions) ? $guardrail->conditions : [],
            ])
            ->values()
            ->all();

        return array_values([...$staticGuardrails, ...$dbGuardrails]);
    }

    private function guardrailsCacheKey(string $tenantId): string
    {
        return "autopilot:guardrails:tenant:{$tenantId}";
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function matchesConditions(
        array $conditions,
        string $actionType,
        array $input,
        array $output,
        string $ruleType,
    ): bool {
        // M-01: Encode uma vez e cache para evitar re-encode em cada condition check
        $inputJson = json_encode($input, JSON_THROW_ON_ERROR);
        $outputJson = json_encode($output, JSON_THROW_ON_ERROR);
        $inputJsonLower = strtolower($inputJson);
        $outputJsonLower = strtolower($outputJson);

        $metadataKeys = ['source'];
        foreach ($metadataKeys as $metadataKey) {
            unset($conditions[$metadataKey]);
        }

        $supportedKeys = [
            'action_type',
            'tool',
            'tool_call',
            'operation',
            'input_contains',
            'output_contains',
            'pattern',
            'discount_percent_gt',
            'response_tokens_gt',
            'competitor_mentions',
            'match',
        ];

        foreach (array_keys($conditions) as $conditionKey) {
            if (! in_array((string) $conditionKey, $supportedKeys, true)) {
                return false;
            }
        }

        if ($conditions === []) {
            return strtoupper($ruleType) === 'LOG' && $actionType === 'tool_call';
        }

        if (isset($conditions['action_type']) && (string) $conditions['action_type'] !== $actionType) {
            return false;
        }

        if (isset($conditions['tool']) && (string) $conditions['tool'] !== (string) ($input['tool'] ?? '')) {
            return false;
        }

        if (array_key_exists('tool_call', $conditions)) {
            $expectsToolCall = filter_var($conditions['tool_call'], FILTER_VALIDATE_BOOL);
            if ($expectsToolCall !== ($actionType === 'tool_call')) {
                return false;
            }
        }

        if (isset($conditions['operation']) && (string) $conditions['operation'] !== (string) ($input['operation'] ?? '')) {
            return false;
        }

        if (isset($conditions['input_contains'])) {
            if (! str_contains($inputJsonLower, strtolower((string) $conditions['input_contains']))) {
                return false;
            }
        }

        if (isset($conditions['output_contains'])) {
            if (! str_contains($outputJsonLower, strtolower((string) $conditions['output_contains']))) {
                return false;
            }
        }

        if (isset($conditions['pattern'])) {
            $pattern = (string) $conditions['pattern'];

            // M-02: Limitar tamanho do pattern para mitigar ReDoS
            if (mb_strlen($pattern) > 500) {
                Log::warning('[GuardrailEvaluator] Pattern too long, skipping', ['length' => mb_strlen($pattern)]);

                return false;
            }

            // Limitar tamanho do subject para mitigar CPU spike
            $subject = mb_substr($inputJson.$outputJson, 0, 10_000);
            $regexPattern = '/'.str_replace('/', '\/', $pattern).'/iu';

            $result = @preg_match($regexPattern, $subject);

            if ($result === false) {
                Log::warning('[GuardrailEvaluator] Invalid regex pattern', ['pattern' => $pattern]);

                return false;
            }

            if ($result !== 1) {
                return false;
            }
        }

        if (isset($conditions['discount_percent_gt'])) {
            $discount = data_get($input, 'args.discount_percent');
            if (! is_numeric($discount) || (float) $discount <= (float) $conditions['discount_percent_gt']) {
                return false;
            }
        }

        if (isset($conditions['response_tokens_gt'])) {
            $totalTokens = data_get($output, 'raw.total_tokens') ?? data_get($output, 'raw.totalTokens');
            if (! is_numeric($totalTokens) || (int) $totalTokens <= (int) $conditions['response_tokens_gt']) {
                return false;
            }
        }

        if (isset($conditions['competitor_mentions']) && is_array($conditions['competitor_mentions'])) {
            $payload = $inputJsonLower.$outputJsonLower;
            $found = false;

            foreach ($conditions['competitor_mentions'] as $mention) {
                if (str_contains($payload, strtolower((string) $mention))) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        if (isset($conditions['match']) && is_array($conditions['match'])) {
            $payload = [
                'action_type' => $actionType,
                'input' => $input,
                'output' => $output,
            ];

            foreach ($conditions['match'] as $key => $value) {
                if (data_get($payload, (string) $key) !== $value) {
                    return false;
                }
            }
        }

        return true;
    }
}
