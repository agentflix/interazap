<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers\Internal;

use Domain\Chat\Models\ChatInstance;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Controller interno consumido pelo Gateway para operações sobre ChatInstances.
 *
 * Protegido pelo middleware `gateway.secret`.
 * Queries sem tenant isolation pois o Gateway é trusted e resolve cross-tenant.
 *
 * @category Controllers
 */
final class InternalChatInstanceController extends BaseController
{
    /**
     * GET /api/internal/chat/instances/by-webhook-token/{token}
     *
     * Lookup global por webhook_token; fallback por settings_json->>'token'.
     */
    public function byWebhookToken(string $token): JsonResponse
    {
        $instance = ChatInstance::withoutGlobalScope(TenantScope::class)
            ->where('webhook_token', $token)
            ->first();

        if ($instance === null) {
            $instance = ChatInstance::withoutGlobalScope(TenantScope::class)
                ->whereRaw("settings_json->>'token' = ?", [$token])
                ->first();
        }

        if ($instance === null) {
            return $this->error('Instance not found', 404);
        }

        return $this->success($this->formatInstance($instance));
    }

    /**
     * GET /api/internal/chat/instances/{id}
     *
     * Lookup global por ID.
     */
    public function show(string $id): JsonResponse
    {
        $instance = ChatInstance::withoutGlobalScope(TenantScope::class)
            ->find($id);

        if ($instance === null) {
            return $this->error('Instance not found', 404);
        }

        return $this->success($this->formatInstance($instance));
    }

    /**
     * PATCH /api/internal/chat/instances/{id}/connection-status
     *
     * Atualiza status e last_status_at da instância.
     */
    public function updateConnectionStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status'       => ['required', 'string', 'max:64'],
            'connected_at' => ['nullable', 'string'],
        ]);

        $exists = ChatInstance::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->exists();

        if (! $exists) {
            return $this->error('Instance not found', 404);
        }

        $updateData = [
            'status'     => $validated['status'],
            'updated_at' => now(),
        ];

        if (! empty($validated['connected_at'])) {
            $updateData['last_status_at'] = Carbon::parse($validated['connected_at']);
        }

        ChatInstance::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->update($updateData);

        return response()->json(['updated' => true]);
    }

    /**
     * GET /api/internal/chat/instances
     *
     * Listagem filtrada por tenant_id; provider e status opcionais.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'uuid'],
            'provider'  => ['nullable', 'string', 'max:64'],
            'status'    => ['nullable', 'string', 'max:64'],
        ]);

        $query = ChatInstance::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $validated['tenant_id']);

        if (isset($validated['provider'])) {
            $query->where('provider', $validated['provider']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $data = $query->get()->map(static fn (ChatInstance $i): array => [
            'instance_id' => (string) $i->id,
            'tenant_id'   => (string) $i->tenant_id,
            'provider'    => (string) $i->provider,
            'status'      => (string) $i->status,
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * @return array{instance_id: string, tenant_id: string, provider: string, status: string, settings: array<string, mixed>}
     */
    private function formatInstance(ChatInstance $instance): array
    {
        return [
            'instance_id' => (string) $instance->id,
            'tenant_id'   => (string) $instance->tenant_id,
            'provider'    => (string) $instance->provider,
            'status'      => (string) $instance->status,
            'settings'    => $instance->settings_json ?? [],
        ];
    }
}
