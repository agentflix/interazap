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
