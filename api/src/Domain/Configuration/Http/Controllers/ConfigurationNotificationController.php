<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Controllers;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Actions\ConfigurationNotificationActions;
use Domain\Configuration\Http\Requests\ConfigurationNotificationPreferenceBulkRequest;
use Domain\Configuration\Http\Requests\ConfigurationNotificationPreferenceRequest;
use Domain\Configuration\Http\Requests\ConfigurationPushSubscriptionRequest;
use Domain\Configuration\Http\Resources\ConfigurationNotificationPreferenceResource;
use Domain\Configuration\Http\Resources\ConfigurationNotificationResource;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de Notificações e Preferências.
 *
 * Gerencia a entrega de alertas do sistema e as configurações de canais
 * (UI, Email, Webhook) para cada tipo de evento, conforme as preferências do usuário.
 *
 * @category Controllers
 */
final class ConfigurationNotificationController extends BaseController
{
    /** Injeta as actions de notificação. */
    public function __construct(
        private readonly ConfigurationNotificationActions $actions
    ) {}

    /**
     * Listar notificações não lidas do usuário.
     *
     * @param  Request  $request  Solicitação HTTP com filtros/limites.
     * @return JsonResponse Lista de notificações e contagem total não lida.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConfigurationNotificationPreference::class);

        /** @var AuthUser $user */
        $user = Auth::user();
        $limit = (int) ($request->integer('limit') ?: 20);

        $notifications = $this->actions->listUnread($user, $limit);
        $unreadCount = $this->actions->unreadCount($user);

        return response()->json([
            'data' => ConfigurationNotificationResource::collection($notifications),
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Marcar uma notificação específica como lida.
     *
     * @param  string  $id  Identificador UUID da notificação.
     * @return JsonResponse Confirmação ou erro 404.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $this->authorize('update', ConfigurationNotificationPreference::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $ok = $this->actions->markAsRead($user, $id);

        return $ok
            ? $this->success(null, 'Notification marked as read')
            : $this->notFound('Notification not found');
    }

    /**
     * Marcar todas as notificações do usuário como lidas.
     *
     * @return JsonResponse Quantidade de notificações alteradas.
     */
    public function markAllAsRead(): JsonResponse
    {
        $this->authorize('update', ConfigurationNotificationPreference::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $count = $this->actions->markAllAsRead($user);

        return response()->json([
            'count' => $count,
            'message' => 'Marked notifications as read',
        ]);
    }

    /**
     * Obter o conjunto completo de preferências de notificação do usuário.
     *
     * @return JsonResponse Lista de preferências com metadados de canais e tipos.
     */
    public function preferences(): JsonResponse
    {
        $this->authorize('viewAny', ConfigurationNotificationPreference::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $preferences = $this->actions->getAllPreferences($user);

        return response()->json([
            'success' => true,
            'data' => ConfigurationNotificationPreferenceResource::collection(collect($preferences)->values()),
            'types' => ConfigurationNotificationPreference::TYPES,
            'channels' => ConfigurationNotificationPreference::CHANNELS,
        ]);
    }

    /**
     * Atualizar a preferência de um canal específico.
     *
     * @param  string  $type  Tipo de notificação (ex: chat_new_message).
     * @param  ConfigurationNotificationPreferenceRequest  $request  Novos parâmetros.
     * @return JsonResponse Registro de preferência atualizado.
     */
    public function updatePreference(string $type, ConfigurationNotificationPreferenceRequest $request): JsonResponse
    {
        $this->authorize('update', ConfigurationNotificationPreference::class);

        if (! array_key_exists($type, ConfigurationNotificationPreference::TYPES)) {
            return $this->error('Invalid notification type', 422);
        }

        /** @var AuthUser $user */
        $user = Auth::user();

        $preference = $this->actions->updatePreference(
            user: $user,
            type: $type,
            channels: $request->input('channels', ['ui']),
            enabled: (bool) $request->input('enabled', true),
            quietStart: $request->input('quiet_start'),
            quietEnd: $request->input('quiet_end'),
        );

        return response()->json([
            'success' => true,
            'data' => new ConfigurationNotificationPreferenceResource($preference),
            'message' => 'Preference updated',
        ]);
    }

    /**
     * Atualizar múltiplas preferências de uma vez (Bulk Update).
     *
     * @param  ConfigurationNotificationPreferenceBulkRequest  $request  Coleção de preferências.
     * @return JsonResponse Lista das preferências atualizadas.
     */
    public function updateAllPreferences(ConfigurationNotificationPreferenceBulkRequest $request): JsonResponse
    {
        $this->authorize('update', ConfigurationNotificationPreference::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $updated = $this->actions->updateAllPreferences($user, $request->input('preferences', []));

        return response()->json([
            'success' => true,
            'data' => ConfigurationNotificationPreferenceResource::collection(collect($updated)),
            'message' => 'Preferences updated',
        ]);
    }

    /**
     * Registrar inscrição push web para o usuário autenticado.
     */
    public function pushSubscribe(ConfigurationPushSubscriptionRequest $request): JsonResponse
    {
        $this->authorize('update', ConfigurationNotificationPreference::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $subscription = $this->actions->upsertPushSubscription(
            user: $user,
            endpoint: (string) $request->string('endpoint'),
            p256dh: (string) data_get($request->input('keys'), 'p256dh', ''),
            auth: (string) data_get($request->input('keys'), 'auth', ''),
            contentEncoding: (string) ($request->input('content_encoding') ?? 'aes128gcm'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'endpoint' => $subscription->endpoint,
                'is_active' => $subscription->is_active,
                'last_seen_at' => $subscription->last_seen_at?->toISOString(),
            ],
            'message' => 'Push subscription saved',
        ]);
    }

    /**
     * Desativar inscrição push web por endpoint.
     */
    public function pushUnsubscribe(Request $request): JsonResponse
    {
        $this->authorize('update', ConfigurationNotificationPreference::class);

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        /** @var AuthUser $user */
        $user = Auth::user();

        $ok = $this->actions->deactivatePushSubscription(
            user: $user,
            endpoint: (string) ($validated['endpoint'] ?? ''),
        );

        return response()->json([
            'success' => true,
            'message' => $ok ? 'Push subscription disabled' : 'Push subscription not found',
        ]);
    }
}
