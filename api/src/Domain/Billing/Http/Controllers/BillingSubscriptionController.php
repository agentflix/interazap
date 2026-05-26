<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Carbon\CarbonImmutable;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Actions\BillingChangePlanAction;
use Domain\Billing\Actions\BillingPlanChangePreviewAction;
use Domain\Billing\DTOs\BillingChangePlanDTO;
use Domain\Billing\Enums\OverageMode;
use Domain\Billing\Http\Requests\BillingChangePlanRequest;
use Domain\Billing\Http\Requests\BillingPlanChangePreviewRequest;
use Domain\Billing\Http\Resources\BillingPlanChangeResource;
use Domain\Billing\Http\Resources\BillingPlanListResource;
use Domain\Billing\Http\Resources\BillingSubscriptionResource;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Billing\Services\BillingCycleCalculator;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para assinatura tenant-facing (plano atual + troca de plano).
 *
 * Fornece acesso ao plano atual, uso de recursos, proxima fatura
 * e prevendo/confirmando troca de plano.
 *
 * @category Controllers
 */
final class BillingSubscriptionController extends BaseController
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
        private readonly BillingPlanChangePreviewAction $previewAction,
        private readonly BillingChangePlanAction $changePlanAction,
        private readonly BillingCycleCalculator $cycleCalculator,
    ) {}

    /**
     * Obter assinatura e uso atual do tenant.
     *
     * @param  Request  $request  HTTP request.
     * @return JsonResponse Assinatura com plano, uso de recursos e proxima fatura.
     */
    public function subscription(Request $request): JsonResponse
    {
        $this->assertPlanAccess($request->user(), false);
        $tenantId = $this->tenantId($request);

        $currentPlan = $this->enforcementService->getCurrentPlan($tenantId);

        // Load tenant for billing cycle and overage data
        $currentTenant = PlatformTenant::with('plan')->find($tenantId);

        $anchorDay = $currentTenant?->billing_cycle_anchor_day ?? 1;
        $now = CarbonImmutable::now();
        // Pass cycle_days for trial plans (FEAT-005); null falls back to monthly anchor behavior
        $cycleDays = $currentPlan?->cycle_days ?? null;
        $cycle = $this->cycleCalculator->calculate($anchorDay, $now, $cycleDays);
        $cycleStartStr = $cycle['cycle_start']->toDateString();
        $cycleEndStr = $cycle['cycle_end']->toDateString();

        $aiUsage = TenantMessageUsage::query()
            ->where('tenant_id', $tenantId)
            ->where('cycle_start', $cycleStartStr)
            ->first();

        $aiLimit = (int) ($currentPlan?->message_limit_monthly ?? 0);
        $aiCurrent = $aiUsage ? (int) $aiUsage->message_count : 0;
        $aiOverage = $aiUsage ? (int) $aiUsage->overage_count : 0;
        $aiPct = $aiLimit > 0 ? min(round(($aiCurrent / $aiLimit) * 100, 1), 100) : 0;
        $aiMode = ($currentTenant?->effectiveOverageMode() ?? OverageMode::STOP)->value;
        $aiOveragePrice = $currentPlan?->overage_price_per_message;

        $monthNames = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        $startLabel = $cycle['cycle_start']->day.'/'.$monthNames[$cycle['cycle_start']->month - 1];
        $endLabel = $cycle['cycle_end']->day.'/'.$monthNames[$cycle['cycle_end']->month - 1];
        $cycleLabel = $startLabel.' – '.$endLabel;

        $usersCurrent = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $instancesCurrent = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $storageUsed = $this->enforcementService->getStorageUsageBytes($tenantId);
        $storageLimit = $this->enforcementService->getEffectiveStorageLimitBytes($tenantId);
        $storageMode = 'LIMITED';
        $negotiationsMode = $currentPlan === null ? 'UNLIMITED' : $currentPlan->negotiations_mode->value;
        $negotiationsLimit = $currentPlan?->negotiations_limit;
        $aiEnabled = $currentPlan === null ? false : (bool) $currentPlan->ai_enabled;
        $currentPlanPrice = $currentPlan === null ? 0.0 : (float) $currentPlan->price_monthly;

        $negotiationsCurrent = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $nextInvoice = BillingInvoice::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->first();

        $creditAvailable = '0.00';

        if ($nextInvoice !== null && is_array($nextInvoice->metadata)) {
            $creditAvailable = (string) ($nextInvoice->metadata['plan_change_credit'] ?? '0.00');
        }

        $payload = [
            'current_plan' => $currentPlan ? [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'slug' => $currentPlan->slug,
                'price_monthly' => number_format((float) $currentPlan->price_monthly, 2, '.', ''),
                'limit_users' => (int) $currentPlan->limit_users,
                'chat_channels_limit' => (int) $currentPlan->chat_channels_limit,
                'storage_mode' => $currentPlan->storage_mode->value,
                'storage_limit_bytes' => $currentPlan->storage_limit_bytes,
                'ai_enabled' => (bool) $currentPlan->ai_enabled,
                'negotiations_mode' => $currentPlan->negotiations_mode->value,
                'negotiations_limit' => $currentPlan->negotiations_limit,
                'cycle_days' => (int) ($currentPlan->cycle_days ?? 30),
                'is_trial' => (bool) ($currentPlan->is_trial ?? false),
            ] : null,
            'usage' => [
                'users' => [
                    'current' => $usersCurrent,
                    'limit' => $currentPlan ? (int) $currentPlan->limit_users : null,
                    'percentage' => $this->percentage($usersCurrent, $currentPlan?->limit_users),
                ],
                'instances' => [
                    'current' => $instancesCurrent,
                    'limit' => $currentPlan ? (int) $currentPlan->chat_channels_limit : null,
                    'percentage' => $this->percentage($instancesCurrent, $currentPlan?->chat_channels_limit),
                ],
                'storage' => [
                    'used_bytes' => $storageUsed,
                    'limit_bytes' => $storageLimit,
                    'used_formatted' => $this->formatBytes($storageUsed),
                    'limit_formatted' => $this->formatBytes((int) $storageLimit),
                    'percentage' => $this->percentage($storageUsed, (int) $storageLimit),
                    'mode' => $storageMode,
                    'is_full' => $storageUsed >= (int) $storageLimit,
                ],
                'negotiations' => [
                    'current' => $negotiationsCurrent,
                    'limit' => $negotiationsLimit,
                    'mode' => $negotiationsMode,
                    'percentage' => $negotiationsLimit ? $this->percentage($negotiationsCurrent, (int) $negotiationsLimit) : null,
                ],
                'ai_messages' => [
                    'current' => $aiCurrent,
                    'limit' => $aiLimit,
                    'percentage' => $aiPct,
                    'overage_count' => $aiOverage,
                    'mode' => $aiMode,
                    'overage_price' => $aiOveragePrice ? (float) $aiOveragePrice : null,
                    'cycle_start' => $cycleStartStr,
                    'cycle_end' => $cycleEndStr,
                    'cycle_label' => $cycleLabel,
                ],
                'ai' => [
                    'enabled' => $aiEnabled,
                ],
            ],
            'next_invoice' => [
                'due_date' => $nextInvoice?->due_date?->format('Y-m-d'),
                'estimated_amount' => $nextInvoice ? number_format((float) $nextInvoice->amount, 2, '.', '') : number_format($currentPlanPrice, 2, '.', ''),
                'credit_available' => number_format((float) $creditAvailable, 2, '.', ''),
            ],
        ];

        return $this->success(new BillingSubscriptionResource($payload), 'Assinatura carregada');
    }

    /**
     * Listar planos disponiveis para o tenant.
     *
     * @param  Request  $request  HTTP request.
     * @return JsonResponse Lista de planos ativos com indicador de plano atual.
     */
    public function plans(Request $request): JsonResponse
    {
        $this->assertPlanAccess($request->user(), false);
        $tenantId = $this->tenantId($request);

        $currentPlan = $this->enforcementService->getCurrentPlan($tenantId);

        $plans = PlatformPlan::query()
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->get()
            ->map(function (PlatformPlan $plan) use ($currentPlan): PlatformPlan {
                $plan->setAttribute('is_current', $currentPlan?->id === $plan->id);

                return $plan;
            });

        return $this->success(BillingPlanListResource::collection($plans), 'Planos carregados');
    }

    /**
     * Gerar preview de troca de plano.
     *
     * @param  BillingPlanChangePreviewRequest  $request  Request com plan_id.
     * @return JsonResponse Preview com creditos, proracao e novo valor.
     */
    public function preview(BillingPlanChangePreviewRequest $request): JsonResponse
    {
        $this->assertPlanAccess($request->user(), true);
        $tenantId = $this->tenantId($request);

        try {
            $preview = $this->previewAction->execute($tenantId, (string) $request->validated('plan_id'));

            return $this->success(new BillingPlanChangeResource($preview), 'Preview gerado');
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * Confirmar troca de plano.
     *
     * @param  BillingChangePlanRequest  $request  Request com plan_id validado.
     * @return JsonResponse Resultado da troca com creditos aplicados.
     */
    public function change(BillingChangePlanRequest $request): JsonResponse
    {
        $this->assertPlanAccess($request->user(), true);
        $tenantId = $this->tenantId($request);
        $userId = (string) $request->user()->id;

        try {
            $result = $this->changePlanAction->execute(
                $tenantId,
                $userId,
                BillingChangePlanDTO::fromRequest($request),
            );

            return $this->success(new BillingPlanChangeResource($result), 'Plano alterado');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->error('Erro de validação', 422, $exception->errors());
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * Verifica se o usuário tem acesso aos endpoints de assinatura, abortando com 401/403 se não tiver.
     *
     * @param  bool  $requiresManage  Se verdadeiro, exige a permissão billing.plan.manage adicionalmente
     */
    private function assertPlanAccess(?AuthUser $user, bool $requiresManage): void
    {
        if ($user === null) {
            abort(401, 'Unauthorized');
        }

        if (! $user->can('billing.view')) {
            abort(403, 'Forbidden');
        }

        if ($requiresManage && ! $user->can('billing.plan.manage')) {
            abort(403, 'Forbidden');
        }

        if ($user->isSuperAdmin() || $user->isInquilino()) {
            return;
        }

        abort(403, 'Forbidden');
    }

    /** Calcula a porcentagem de uso arredondada para uma casa decimal, ou null se não houver limite. */
    private function percentage(int $current, ?int $limit): ?float
    {
        if (! is_numeric($limit) || (int) $limit <= 0) {
            return null;
        }

        $value = ($current * 100) / (int) $limit;

        return round($value, 1);
    }

    /** Formata bytes para unidade legível (B, KB, MB, GB, TB). */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, 1).' '.$units[$unit];
    }
}
