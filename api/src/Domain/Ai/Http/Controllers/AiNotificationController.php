<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\AiNotificationActions;
use Domain\Ai\Http\Requests\AiNotificationMarkAsReadRequest;
use Domain\Ai\Http\Resources\AiNotificationResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller para notificações de vendedores do módulo de IA.
 *
 * Gerencia as notificações enviadas aos vendedores pelos agentes de IA.
 */
final class AiNotificationController extends BaseController
{
    public function __construct(private readonly AiNotificationActions $actions) {}

    /**
     * Lista as notificações do usuário autenticado.
     *
     * @param  Request  $request  Requisição HTTP com filtro unread_only opcional.
     * @return AnonymousResourceCollection Coleção de notificações.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $unreadOnly = $request->boolean('unread_only', false);

        $notifications = $this->actions->list(
            tenantId: $tenantId,
            userId: $userId,
            unreadOnly: $unreadOnly,
        );

        return AiNotificationResource::collection($notifications);
    }

    /**
     * Exibe uma notificação específica.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID da notificação.
     * @return AiNotificationResource|JsonResponse Dados da notificação ou erro 404.
     */
    public function show(Request $request, string $id): AiNotificationResource|JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $notification = $this->actions->find($tenantId, $userId, $id);

        if (! $notification) {
            return response()->json([
                'message' => 'Notification not found.',
            ], 404);
        }

        return new AiNotificationResource($notification);
    }

    /**
     * Marca uma ou mais notificações como lidas.
     *
     * @param  AiNotificationMarkAsReadRequest  $request  Lista de IDs validados.
     * @return JsonResponse Total de notificações marcadas como lidas.
     */
    public function markAsRead(AiNotificationMarkAsReadRequest $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $ids = $request->validated('ids');

        $updated = $this->actions->markAsRead($tenantId, $userId, $ids);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notification(s) marked as read.",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Retorna o total de notificações não lidas do usuário.
     *
     * @param  Request  $request  Requisição HTTP.
     * @return JsonResponse Contagem de notificações não lidas.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $count = $this->actions->unreadCount($tenantId, $userId);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }
}
