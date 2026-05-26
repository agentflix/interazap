<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMNegotiationTaskUserActions;
use Domain\CRM\Http\Resources\CRMNegotiationTaskResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para tarefas de negociação atribuídas ao usuário autenticado.
 *
 * Retorna todas as tarefas do tenant atribuídas ao usuário da sessão. Requer autenticação Sanctum.
 */
final class CRMNegotiationTaskUserController extends BaseController
{
    /**
     * @param  CRMNegotiationTaskUserActions  $actions  Ação de listagem de tarefas por usuário.
     */
    public function __construct(
        private readonly CRMNegotiationTaskUserActions $actions
    ) {}

    /**
     * Lista tarefas de negociação atribuídas ao usuário autenticado.
     *
     * @param  Request  $request  Dados da requisição.
     * @return JsonResponse Lista de tarefas do usuário.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $userId = (string) $request->user()?->id;
        $tasks = $this->actions->listForUser($tenantId, $userId);

        return $this->success(
            ['tasks' => CRMNegotiationTaskResource::collection($tasks)],
            'Tarefas do usuário'
        );
    }
}
