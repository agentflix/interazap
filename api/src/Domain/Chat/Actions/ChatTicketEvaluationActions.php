<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatTicketEvaluationDTO;
use Domain\Chat\Models\ChatTicketEvaluation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Casos de Uso para Avaliação de Tickets (CSAT).
 *
 * Centraliza a gestão de feedbacks de tickets, permitindo listagem e
 * registro inicial da solicitação de avaliação.
 *
 * @category Actions
 */
final class ChatTicketEvaluationActions
{
    /**
     * Listar todas as avaliações recebidas pelo tenant com paginação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return LengthAwarePaginator Paginador com registros de avaliação.
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        return ChatTicketEvaluation::query()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate();
    }

    /**
     * Criar uma nova requisição de avaliação para um ticket.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatTicketEvaluationDTO  $dto  Dados estruturados da avaliação.
     * @return ChatTicketEvaluation Modelo criado.
     */
    public function create(string $tenantId, ChatTicketEvaluationDTO $dto): ChatTicketEvaluation
    {
        return ChatTicketEvaluation::query()->create([
            'tenant_id' => $tenantId,
            'token' => (string) Str::orderedUuid(),
            ...$dto->toArray(),
        ]);
    }
}
