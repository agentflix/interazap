<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMNegotiationTaskUserActions;
use Domain\CRM\Http\Resources\CRMNegotiationTaskResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lista tarefas atribuídas ao usuário autenticado.
 */
final class CRMNegotiationTaskUserController extends BaseController
{
    public function __construct(
        private readonly CRMNegotiationTaskUserActions $actions
    ) {}

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
