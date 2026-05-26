<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Models\ChatTicketEvaluation;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Controller público para renderização da página de avaliação CSAT.
 *
 * Serve a view HTML pública que permite ao cliente avaliar o atendimento via token.
 */
final class ChatTicketEvaluationPublicPageController
{
    /**
     * Renderizar a página pública de avaliação para um token válido e ainda não submetido.
     *
     * @param  string  $token  Token único da avaliação vinculado ao ticket.
     * @return Factory|View View HTML da avaliação ou 404 se o token for inválido/já usado.
     */
    public function __invoke(string $token): Factory|View
    {
        ChatTicketEvaluation::query()
            ->where('token', $token)
            ->whereNull('submitted_at')
            ->firstOrFail();

        return view('public.evaluation', [
            'token' => $token,
        ]);
    }
}
