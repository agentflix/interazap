<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentChannel;
use Domain\Ai\Models\AiAgentSkill;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\CRM\Models\CRMDepartment;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformTenantBootstrapCatalogService;
use Domain\Shared\Support\TenantContext;
use Illuminate\Support\Str;

/**
 * Bootstrap de dados padrão para tenant recém-criado.
 */
final class PlatformTenantBootstrapAction
{
    public function __construct(
        private readonly PlatformTenantBootstrapCatalogService $catalogService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(PlatformTenant $tenant, bool $dryRun = false): array
    {
        $segment = $this->resolveSegment($tenant);
        $catalog = $this->catalogService->forSegmentCode($segment->code);

        $report = [
            'tenant_id' => $tenant->id,
            'segment_id' => $segment->id,
            'segment_code' => $segment->code,
            'dry_run' => $dryRun,
            'agents' => 0,
            'agent_skills' => 0,
            'agent_channels' => 0,
            'prompt' => 0,
            'playbooks' => 0,
            'tools' => 0,
            'guardrails' => 0,
            'funnels' => 0,
            'funnel_steps' => 0,
            'loss_reasons' => 0,
            'tags' => 0,
            'departments' => 0,
        ];

        if ($dryRun) {
            return $report;
        }

        TenantContext::run($tenant->id, function () use ($tenant, $segment, $catalog, &$report): void {
            $report['prompt'] = $this->syncPrompt($tenant, $segment, (string) $catalog['prompt_suffix']);

            $agentSync = $this->syncAgents($tenant, (array) ($catalog['agents'] ?? []));
            $report['agents'] = $agentSync['agents'];
            $report['agent_skills'] = $agentSync['skills'];
            $report['agent_channels'] = $agentSync['channels'];

            $funnelSync = $this->syncFunnels($tenant, (array) $catalog['funnels']);
            $report['funnels'] = $funnelSync['funnels'];
            $report['funnel_steps'] = $funnelSync['steps'];

            $report['loss_reasons'] = $this->syncLossReasons($tenant, (array) $catalog['loss_reasons']);
            $report['tags'] = $this->syncTags($tenant, (array) $catalog['tags']);
            $report['departments'] = $this->syncDepartments($tenant, (array) $catalog['departments']);
        });

        logger()->info('PlatformTenant bootstrap completed', $report);

        return $report;
    }

    private function resolveSegment(PlatformTenant $tenant): AiPromptSegment
    {
        if (is_string($tenant->segment_id) && $tenant->segment_id !== '') {
            $segment = AiPromptSegment::query()->find($tenant->segment_id);
            if ($segment instanceof AiPromptSegment) {
                return $segment;
            }
        }

        $generalSegment = AiPromptSegment::getGeneral();

        if (! $generalSegment instanceof AiPromptSegment) {
            throw new \RuntimeException('GENERAL segment not found for tenant bootstrap.');
        }

        $tenant->forceFill(['segment_id' => $generalSegment->id])->save();

        return $generalSegment;
    }

    private function syncPrompt(PlatformTenant $tenant, AiPromptSegment $segment, string $promptSuffix): int
    {
        $content = trim($segment->content."\n\n".$promptSuffix);

        AiPromptTenant::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'segment_id' => $segment->id,
                'content' => $content,
                'version' => 1,
                'validation_status' => AiPromptValidationStatus::APPROVED->value,
                'validated_hash' => hash('sha256', $content),
                'validated_at' => now(),
                'guardian_analysis' => 'Bootstrap default prompt approved.',
                'is_active' => true,
            ]
        );

        return 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $agents
     * @return array{agents: int, skills: int, channels: int}
     */
    private function syncAgents(PlatformTenant $tenant, array $agents): array
    {
        $agentCount = 0;
        $skillCount = 0;
        $channelCount = 0;
        $fileCount = 0;

        foreach ($agents as $agentData) {
            $name = trim((string) ($agentData['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $tools = $this->normalizeStringList((array) ($agentData['tools'] ?? []));

            $metadata = is_array($agentData['metadata'] ?? null)
                ? (array) $agentData['metadata']
                : [];
            $metadata['tool_names'] = $tools;

            $agent = AiAgent::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                ],
                [
                    'type' => (string) ($agentData['type'] ?? 'general'),
                    'model_id' => (string) ($agentData['model_id'] ?? 'gpt-4o-mini'),
                    'system_prompt' => (string) ($agentData['system_prompt'] ?? ''),
                    'max_tokens' => (int) ($agentData['max_tokens'] ?? 2048),
                    'temperature' => (float) ($agentData['temperature'] ?? 0.7),
                    'top_p' => (float) ($agentData['top_p'] ?? 1.0),
                    'is_active' => (bool) ($agentData['is_active'] ?? true),
                    'classifier_model' => isset($agentData['classifier_model'])
                        ? (string) $agentData['classifier_model']
                        : null,
                    'token_budget_input' => (int) ($agentData['token_budget_input'] ?? 0),
                    'token_budget_output' => (int) ($agentData['token_budget_output'] ?? 0),
                    'fallback_message' => isset($agentData['fallback_message'])
                        ? (string) $agentData['fallback_message']
                        : null,
                    'metadata' => $metadata,
                ]
            );

            $agent->forceFill([
                'type' => (string) ($agentData['type'] ?? $agent->type),
                'model_id' => (string) ($agentData['model_id'] ?? $agent->model_id),
                'system_prompt' => isset($agentData['system_prompt'])
                    ? (string) $agentData['system_prompt']
                    : $agent->system_prompt,
                'max_tokens' => (int) ($agentData['max_tokens'] ?? $agent->max_tokens),
                'temperature' => (float) ($agentData['temperature'] ?? $agent->temperature),
                'top_p' => (float) ($agentData['top_p'] ?? $agent->top_p),
                'is_active' => (bool) ($agentData['is_active'] ?? $agent->is_active),
                'classifier_model' => array_key_exists('classifier_model', $agentData)
                    ? (string) $agentData['classifier_model']
                    : $agent->classifier_model,
                'token_budget_input' => (int) ($agentData['token_budget_input'] ?? $agent->token_budget_input),
                'token_budget_output' => (int) ($agentData['token_budget_output'] ?? $agent->token_budget_output),
                'fallback_message' => array_key_exists('fallback_message', $agentData)
                    ? (string) $agentData['fallback_message']
                    : $agent->fallback_message,
                'metadata' => $metadata,
            ])->save();

            $agentCount++;

            $skillCount += $this->syncAgentSkills($tenant, $agent, (array) ($agentData['skills'] ?? []));
            $channelCount += $this->syncAgentChannels($tenant, $agent, (array) ($agentData['channels'] ?? []));
            $fileCount += $this->syncAgentFiles($tenant, $agent, (array) ($agentData['files'] ?? []));
        }

        return [
            'agents' => $agentCount,
            'skills' => $skillCount,
            'channels' => $channelCount,
            'files' => $fileCount,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $skills
     */
    private function syncAgentSkills(PlatformTenant $tenant, AiAgent $agent, array $skills): int
    {
        $count = 0;

        foreach ($skills as $skillData) {
            $skillName = trim((string) ($skillData['name'] ?? ''));
            if ($skillName === '') {
                continue;
            }

            AiAgentSkill::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'agent_id' => $agent->id,
                    'name' => $skillName,
                ],
                [
                    'description' => isset($skillData['description'])
                        ? (string) $skillData['description']
                        : null,
                    'is_active' => (bool) ($skillData['is_active'] ?? true),
                    'metadata' => is_array($skillData['metadata'] ?? null)
                        ? (array) $skillData['metadata']
                        : null,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function syncAgentFiles(PlatformTenant $tenant, AiAgent $agent, array $files): int
    {
        $count = 0;

        foreach ($files as $fileData) {
            $slug = trim((string) ($fileData['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            \Domain\Ai\Models\AiAgentFile::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'agent_id' => $agent->id,
                    'slug' => $slug,
                ],
                [
                    'id' => (string) Str::orderedUuid(),
                    'content' => isset($fileData['content']) ? (string) $fileData['content'] : null,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $channels
     */
    private function syncAgentChannels(PlatformTenant $tenant, AiAgent $agent, array $channels): int
    {
        $count = 0;

        foreach ($channels as $channelData) {
            $channel = null;
            $externalRef = null;
            $metadata = null;
            $isActive = true;

            if (is_string($channelData)) {
                $channel = trim($channelData);
            } elseif (is_array($channelData)) {
                $channel = trim((string) ($channelData['channel'] ?? ''));
                $externalRef = isset($channelData['external_ref'])
                    ? (string) $channelData['external_ref']
                    : null;
                $metadata = is_array($channelData['metadata'] ?? null)
                    ? (array) $channelData['metadata']
                    : null;
                $isActive = (bool) ($channelData['is_active'] ?? true);
            }

            if (! is_string($channel) || $channel === '') {
                continue;
            }

            AiAgentChannel::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'agent_id' => $agent->id,
                    'channel' => $channel,
                    'external_ref' => $externalRef,
                ],
                [
                    'is_active' => $isActive,
                    'metadata' => $metadata,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function normalizeStringList(array $items): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $items
        ), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $funnels
     * @return array{funnels: int, steps: int}
     */
    private function syncFunnels(PlatformTenant $tenant, array $funnels): array
    {
        $funnelCount = 0;
        $stepCount = 0;

        foreach ($funnels as $funnelData) {
            $funnel = CRMNegotiationFunnel::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => (string) $funnelData['name'],
                ],
                [
                    'id' => (string) Str::orderedUuid(),
                    'is_active' => true,
                ]
            );

            $funnelCount++;

            foreach ((array) ($funnelData['steps'] ?? []) as $stepData) {
                CRMNegotiationFunnelStep::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'crm_negotiation_funnel_id' => $funnel->id,
                        'order' => (int) $stepData['order'],
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'name' => (string) $stepData['name'],
                        'color' => (string) ($stepData['color'] ?? '#3B82F6'),
                        'is_active' => true,
                    ]
                );

                $stepCount++;
            }
        }

        return ['funnels' => $funnelCount, 'steps' => $stepCount];
    }

    /**
     * @param  array<int, string>  $reasons
     */
    private function syncLossReasons(PlatformTenant $tenant, array $reasons): int
    {
        $count = 0;

        foreach ($reasons as $reason) {
            CRMReasonLoss::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $reason,
                ],
                [
                    'id' => (string) Str::orderedUuid(),
                    'description' => null,
                    'requires_comment' => false,
                    'is_active' => true,
                    'position' => 0,
                    'usage_count' => 0,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function syncTags(PlatformTenant $tenant, array $tags): int
    {
        $count = 0;

        foreach ($tags as $tag) {
            CRMTag::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $tag,
                ],
                [
                    'id' => (string) Str::orderedUuid(),
                    'color' => '#'.substr(md5($tag), 0, 6),
                    'category' => 'default',
                    'is_active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    private const array DEPARTMENT_DESCRIPTIONS = [
        'Comercial' => 'Departamento responsável pela gestão da equipe de vendas, prospecção de clientes e fechamento de negócios.',
        'Suporte' => 'Departamento dedicado ao suporte técnico, resolução de problemas e assistência aos clientes.',
        'Customer Success' => 'Departamento focado no sucesso do cliente, onboarding e retenção.',
        'Financeiro' => 'Departamento responsável pela gestão financeira, controle de contas a pagar e a receber.',
        'Pré-vendas' => 'Departamento focado na qualificação de leads, pré-avaliação e preparação para a equipe de vendas.',
        'Vendas' => 'Departamento responsável pela gestão da equipe de vendas, prospecção de clientes e fechamento de negócios.',
        'Onboarding' => 'Departamento responsável pelo acolhimento e integração de novos clientes.',
        'Vendas Online' => 'Departamento focado em vendas através de canais digitais e e-commerce.',
        'SAC' => 'Serviço de Atendimento ao Cliente, responsável pelo suporte e resolução de demandas.',
        'Logística' => 'Departamento responsável por transporte, armazenagem e distribuição de produtos.',
        'Pós-venda' => 'Departamento focado no acompanhamento pós-venda e satisfação do cliente.',
        'Recepção' => 'Departamento de recepção e primeiro atendimento ao cliente.',
        'Agendamento' => 'Departamento responsável pelo agendamento de consultas, reuniões e serviços.',
        'Atendimento' => 'Departamento focado no suporte e atendimento ao cliente, resolução de dúvidas.',
        'Locação' => 'Departamento focado em imóveis para locação, visitas e contratos.',
        'Documental' => 'Departamento responsável pela gestão documental e paperwork de imóveis.',
    ];

    /**
     * @param  array<int, string>  $departments
     */
    private function syncDepartments(PlatformTenant $tenant, array $departments): int
    {
        $count = 0;

        foreach ($departments as $department) {
            $description = self::DEPARTMENT_DESCRIPTIONS[$department]
                ?? 'Departamento padrão do sistema.';

            CRMDepartment::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $department,
                ],
                [
                    'id' => (string) Str::orderedUuid(),
                    'description' => $description,
                    'is_active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }
}
