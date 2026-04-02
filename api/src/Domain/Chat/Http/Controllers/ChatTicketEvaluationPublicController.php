<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatTicketEvaluationPublicActions;
use Domain\Chat\Http\Requests\ChatTicketEvaluationPublicRequest;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controlador Público para Avaliação de Tickets.
 *
 * Permite que clientes externos enviem feedbacks sobre o atendimento recebido
 * utilizando um link seguro com token, sem necessidade de autenticação no sistema.
 *
 * @category Controllers
 */
final class ChatTicketEvaluationPublicController extends BaseController
{
    public function __construct(
        private readonly ChatTicketEvaluationPublicActions $actions
    ) {}

    /**
     * Validar token e retornar status da avaliação.
     *
     * @param  string  $token  Token de autenticação da avaliação.
     * @return JsonResponse Dados do token (válido ou já submetido).
     */
    public function show(string $token): JsonResponse
    {
        $evaluation = ChatTicketEvaluation::query()
            ->where('token', $token)
            ->first();

        if (! $evaluation) {
            return $this->error('Avaliação não encontrada.', 404);
        }

        if ($evaluation->submitted_at !== null) {
            return $this->success([
                'submitted' => true,
                'message' => 'Esta avaliação já foi enviada.',
            ]);
        }

        return $this->success([
            'submitted' => false,
            'token' => $token,
        ]);
    }

    /**
     * Processar a submissão de feedback via token de acesso único.
     *
     * @param  ChatTicketEvaluationPublicRequest  $request  Solicitação validada de nota e comentário.
     * @param  string  $token  Token de autenticação da avaliação vinculado ao ticket.
     * @return JsonResponse Confirmação de recebimento.
     */
    public function submit(ChatTicketEvaluationPublicRequest $request, string $token): JsonResponse
    {
        $data = $request->validated();

        $this->actions->submit($token, (int) $data['rating'], $data['comment'] ?? null);

        return $this->success([
            'message' => 'Obrigado pelo feedback!',
        ]);
    }
}
