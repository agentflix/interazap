<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Actions\PlatformLeadActions;
use Domain\Platform\DTOs\PlatformLeadDTO;
use Domain\Platform\Http\Requests\PlatformLeadStoreRequest;
use Domain\Platform\Http\Resources\PlatformLeadResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controlador público de leads da plataforma InteraZap.
 *
 * Recebe submissões da landing page e modais de saída e armazena-as
 * como prospects internos do produto. Não exige autenticação.
 *
 * @see PlatformLeadActions
 */
final class PlatformLeadController extends BaseController
{
    /**
     * @param  PlatformLeadActions  $actions  Action de captura de leads.
     */
    public function __construct(
        private readonly PlatformLeadActions $actions,
    ) {}

    /**
     * Captura um novo lead vindo da landing.
     *
     * Endpoint público (sem auth). Honeypot e deduplicação tratados na Action.
     *
     * @param  PlatformLeadStoreRequest  $request  Requisição validada.
     * @return JsonResponse 201 com { id, name, source, created_at }.
     */
    public function store(PlatformLeadStoreRequest $request): JsonResponse
    {
        $dto = PlatformLeadDTO::fromRequest($request, $request);

        $lead = $this->actions->store($dto);

        return $this->created(new PlatformLeadResource($lead), 'Lead recebido com sucesso');
    }
}
