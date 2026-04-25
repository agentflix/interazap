<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\LookupInstanceByPhoneNumberAction;
use Domain\Chat\Actions\VerifyContactWindowAction;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para verificação de janela de contato e lookup de instância.
 *
 * Endpoints para verificação de janela 24h (usuário autenticado) e
 * resolução de phone_number_id (Gateway via GATEWAY_SECRET).
 *
 * @category Controllers
 */
final class ChatWindowController extends BaseController
{
    public function __construct(
        private readonly VerifyContactWindowAction $verifyWindowAction,
        private readonly LookupInstanceByPhoneNumberAction $lookupAction,
    ) {}

    /**
     * Verifica se o contato está dentro da janela de 24h.
     * GET /api/chat/contacts/{id}/window-status
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  UUID do contato.
     * @return JsonResponse Status da janela de 24h.
     */
    public function windowStatus(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $result = $this->verifyWindowAction->execute($tenantId, $id);

        return $this->success($result->toArray());
    }

    /**
     * Resolve phone_number_id para ChatInstance.
     * GET /api/chat/instances/by-phone-number/{phoneNumberId}
     *
     * Chamado pelo Gateway via GATEWAY_SECRET para normalizar webhooks.
     * NÃO usa isolamento de tenant pois phone_number_id é único globalmente.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $phoneNumberId  Phone Number ID da Meta.
     * @return JsonResponse Dados da instância ou 404.
     */
    public function lookupByPhoneNumber(Request $request, string $phoneNumberId): JsonResponse
    {
        $result = $this->lookupAction->execute($phoneNumberId);

        if (! $result) {
            return $this->error('Instance not found', 404);
        }

        return $this->success($result->toArray());
    }
}
