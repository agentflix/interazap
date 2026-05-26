<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Billing\Http\Requests\BillingUsageCheckRequest;
use Domain\Billing\Jobs\CheckUsageThresholdsJob;
use Domain\Billing\Models\AiMessageUsageFailedLog;
use Domain\Billing\Services\UsageCounterService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para endpoints de bilhetagem de uso de mensagens IA.
 *
 * Fornece check-and-increment (controle de cota de IA) e registro de falha-aberta
 * para casos em que o rastreamento falha mas a resposta IA prossegue (fail-open).
 */
final class BillingUsageController extends BaseController
{
    public function __construct(
        private readonly UsageCounterService $usageCounter,
    ) {}

    /**
     * Verifica se a mensagem é permitida e incrementa o contador de uso do ciclo.
     *
     * Retorna o resultado de uso: permitido, contador atual, limite, modo e flag de excedente.
     * Despacha o job de verificação de limiares de forma assíncrona.
     *
     * @param  BillingUsageCheckRequest  $request  Payload com tenant_id, channel e ai_turn_id
     * @return JsonResponse Resultado de uso serializado pelo CheckAndIncrementResult
     */
    public function check(BillingUsageCheckRequest $request): JsonResponse
    {
        $tenantId = (string) $request->validated('tenant_id');
        $channel = (string) $request->validated('channel');
        $aiTurnId = (string) $request->validated('ai_turn_id');

        $result = $this->usageCounter->checkAndIncrement($tenantId, $channel, $aiTurnId);

        CheckUsageThresholdsJob::dispatch($tenantId, $result->cycleStart);

        return $this->success($result->toArray(), 'Usage checked');
    }

    /**
     * Registra uma tentativa de uso falha-aberta para reconciliação posterior.
     *
     * Chamado quando o gateway IA não consegue contatar este endpoint mas
     * prossegue com a resposta (fail-open). O log é reconciliado pelo ReconcileFailedUsageJob.
     *
     * @param  Request  $request  Payload com tenant_id, ai_turn_id, channel, attempted_at e reason
     * @return JsonResponse Dados do registro criado (idempotente por ai_turn_id)
     */
    public function logFailure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'ai_turn_id' => ['required', 'uuid'],
            'channel' => ['required', 'string', 'max:20'],
            'attempted_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $log = AiMessageUsageFailedLog::query()->firstOrCreate(
            ['ai_turn_id' => $data['ai_turn_id']],
            [
                'tenant_id' => $data['tenant_id'],
                'channel' => $data['channel'],
                'attempted_at' => $data['attempted_at'],
                'reason' => $data['reason'] ?? null,
                'reconciled_at' => null,
            ]
        );

        return $this->created($log->only([
            'id', 'tenant_id', 'ai_turn_id', 'channel', 'attempted_at', 'reason',
        ]), 'Failure logged');
    }
}
