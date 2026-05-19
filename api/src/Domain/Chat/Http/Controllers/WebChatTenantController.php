<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller invokable para retornar dados públicos do tenant no WebChat.
 *
 * Usa DB::table para evitar importar Domain\Platform e respeitar as regras
 * de dependência entre bounded contexts.
 *
 * @category Controllers
 */
final class WebChatTenantController extends BaseController
{
    /**
     * Retorna o nome do tenant ativo a partir do ID público.
     *
     * GET /api/webchat/tenant/{tenantId}
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $tenantId  Identificador UUID do tenant.
     * @return JsonResponse Dados do tenant ou 404.
     */
    public function __invoke(Request $request, string $tenantId): JsonResponse
    {
        $tenant = DB::table('platform_tenants')
            ->where('id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->select(['id', 'name'])
            ->first();

        if (! $tenant) {
            return $this->notFound('Empresa não encontrada');
        }

        return $this->success(['name' => $tenant->name]);
    }
}
