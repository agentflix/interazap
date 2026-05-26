<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\Configuration\Events\EvaluationLowScoreEvent;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso para avaliação pública de ticket.
 */
final class ChatTicketEvaluationPublicActions
{
    /**
     * Registra a avaliação do cliente para um ticket via token público.
     *
     * Localiza a avaliação pelo token, preenche nota e comentário, e dispara
     * evento de baixa pontuação se a nota for menor ou igual ao corte configurado.
     *
     * @param  string  $token  Token público único da avaliação.
     * @param  int  $rating  Nota de 1 a 5 fornecida pelo cliente.
     * @param  string|null  $comment  Comentário opcional do cliente.
     * @return ChatTicketEvaluation Avaliação atualizada.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se o token for inválido ou já submetido.
     */
    public function submit(string $token, int $rating, ?string $comment): ChatTicketEvaluation
    {
        return DB::transaction(function () use ($token, $rating, $comment): ChatTicketEvaluation {
            $evaluation = ChatTicketEvaluation::query()
                ->where('token', $token)
                ->whereNull('submitted_at')
                ->lockForUpdate()
                ->firstOrFail();

            $evaluation->rating = $rating;
            $evaluation->comment = $comment;
            $evaluation->submitted_at = now();
            $evaluation->save();

            $ticket = $evaluation->ticket()->with('instance')->firstOrFail();
            $instance = $ticket->instance;
            $cutoff = (int) ($instance->evaluation_cutoff_score ?? 3);

            if ($rating <= $cutoff) {
                EvaluationLowScoreEvent::dispatch(
                    (string) $evaluation->tenant_id,
                    (string) $evaluation->ticket_id,
                    (string) $evaluation->id,
                    $rating,
                );
            }

            return $evaluation;
        });
    }
}
