<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Carbon\CarbonImmutable;
use Domain\Billing\DTOs\CheckAndIncrementResult;
use Domain\Billing\Enums\OverageMode;
use Domain\Billing\Models\AiMessageUsageFailedLog;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;

/**
 * Gerencia contagem de uso de mensagens IA por tenant e ciclo de billing.
 *
 * Usa o {@see BillingCycleCalculator} para determinar o ciclo atual,
 * suportando planos mensais com âncora customizada e planos trial com
 * duração fixa em dias.
 */
final class UsageCounterService
{
    public function __construct(
        private readonly BillingCycleCalculator $cycleCalculator,
    ) {}

    /**
     * Verifica e incrementa o uso de mensagens IA para o ciclo atual do tenant.
     *
     * Executa dentro de uma transação com `lockForUpdate` para serializar
     * requisições concorrentes do mesmo tenant e evitar race conditions.
     *
     * Fluxo de idempotência (em ordem de verificação):
     *  1. Chave em `ai_usage_idempotency_keys` → retorna resultado cacheado
     *  2. Log reconciliado em `ai_message_usage_failed_logs` → marca como permitido
     *  3. Caso novo → incrementa contador e persiste chave de idempotência
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $channel  Canal de origem da mensagem (ex: whatsapp, webchat)
     * @param  string  $aiTurnId  ID único do turno de IA (chave de idempotência)
     * @return CheckAndIncrementResult Resultado com allowed, current, limit, mode e isOverage
     */
    public function checkAndIncrement(
        string $tenantId,
        string $channel,
        string $aiTurnId,
    ): CheckAndIncrementResult {
        return DB::transaction(function () use ($tenantId, $aiTurnId): CheckAndIncrementResult {
            $tenant = PlatformTenant::with('plan')->findOrFail($tenantId);
            $plan = $tenant->plan;
            $limit = $plan !== null ? ($plan->message_limit_monthly ?? 0) : 0;
            $mode = $tenant->effectiveOverageMode();

            $anchorDay = $tenant->billing_cycle_anchor_day ?? 1;
            $now = CarbonImmutable::now();
            // Only trial plans use fixed-duration cycles; regular plans keep monthly anchor behavior
            $cycleDays = ($plan !== null && $plan->is_trial) ? ($plan->cycle_days ?? null) : null;
            $cycle = $this->cycleCalculator->calculate($anchorDay, $now, $cycleDays);
            $cycleStart = $cycle['cycle_start']->toDateString();
            $cycleEnd = $cycle['cycle_end']->toDateString();

            // Ensure usage row exists, then lock it — serializes concurrent requests for same tenant
            TenantMessageUsage::firstOrCreate(
                ['tenant_id' => $tenantId, 'cycle_start' => $cycleStart],
                [
                    'cycle_end' => $cycleEnd,
                    'message_count' => 0,
                    'overage_count' => 0,
                ]
            );

            $usage = TenantMessageUsage::lockForUpdate()
                ->where('tenant_id', $tenantId)
                ->where('cycle_start', $cycleStart)
                ->firstOrFail();

            // Idempotency check: after acquiring the lock we see committed state from any prior request
            $existing = DB::table('ai_usage_idempotency_keys')
                ->where('ai_turn_id', $aiTurnId)
                ->first();

            if ($existing !== null) {
                /** @var array<string, mixed> $cached */
                $cached = json_decode((string) $existing->result, true);

                return new CheckAndIncrementResult(
                    allowed: (bool) $cached['allowed'],
                    current: (int) $cached['current'],
                    limit: (int) $cached['limit'],
                    mode: OverageMode::from((string) $cached['mode']),
                    isOverage: (bool) $cached['is_overage'],
                    cycleStart: $cycleStart,
                );
            }

            // Also skip if already reconciled via the failed-log replay path
            $alreadyReconciled = AiMessageUsageFailedLog::query()
                ->where('ai_turn_id', $aiTurnId)
                ->whereNotNull('reconciled_at')
                ->exists();

            if ($alreadyReconciled) {
                return new CheckAndIncrementResult(
                    allowed: true,
                    current: $usage->message_count,
                    limit: $limit,
                    mode: $mode,
                    isOverage: $limit > 0 && $usage->message_count > $limit,
                    cycleStart: $cycleStart,
                );
            }

            $current = $usage->message_count;

            if ($current < $limit) {
                $usage->increment('message_count');

                $result = new CheckAndIncrementResult(
                    allowed: true,
                    current: $current + 1,
                    limit: $limit,
                    mode: $mode,
                    isOverage: false,
                    cycleStart: $cycleStart,
                );
            } elseif ($mode === OverageMode::OVERAGE) {
                $usage->increment('overage_count');

                $result = new CheckAndIncrementResult(
                    allowed: true,
                    current: $current,
                    limit: $limit,
                    mode: $mode,
                    isOverage: true,
                    cycleStart: $cycleStart,
                );
            } else {
                $result = new CheckAndIncrementResult(
                    allowed: false,
                    current: $current,
                    limit: $limit,
                    mode: $mode,
                    isOverage: false,
                    cycleStart: $cycleStart,
                );
            }

            DB::table('ai_usage_idempotency_keys')->insertOrIgnore([
                'ai_turn_id' => $aiTurnId,
                'tenant_id' => $tenantId,
                'cycle_start' => $cycleStart,
                'result' => json_encode($result->toArray()),
                'created_at' => $now->toDateTimeString(),
            ]);

            return $result;
        });
    }
}
