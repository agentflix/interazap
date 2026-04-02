<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Models\ChatTicketEvaluation;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Controlador público para renderização da página de avaliação CSAT.
 */
final class ChatTicketEvaluationPublicPageController
{
    /**
     * Renderiza a página pública de avaliação para um token válido e não submetido.
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
