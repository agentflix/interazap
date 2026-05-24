<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers\Internal;

use Domain\Ai\Models\AiAutopilotRun;
use Domain\Chat\Models\ChatTicket;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller interno para validação de acesso a rooms de realtime (WebSocket).
 *
 * Protegido pelo middleware `gateway.secret`.
 * Substitui queries diretas ao PostgreSQL em ws-room-access.service.ts do gateway.
 *
 * @category Controllers
 */
final class InternalRealtimeController extends BaseController
{
    /**
     * GET /api/internal/realtime/room-access
     *
     * Valida se tenant possui ownership de uma room.
     * Query params: room (formato `ticket:{uuid}` ou `run:{uuid}`), tenant_id.
     */
    public function roomAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room'      => ['required', 'string', 'regex:/^(ticket|run):[0-9a-f\-]{36}$/i'],
            'tenant_id' => ['required', 'string', 'uuid'],
        ]);

        $allowed = $this->checkRoomAccess($validated['room'], $validated['tenant_id']);

        return response()->json(['allowed' => $allowed]);
    }

    private function checkRoomAccess(string $room, string $tenantId): bool
    {
        [$type, $id] = explode(':', $room, 2);

        return match ($type) {
            'ticket' => ChatTicket::withoutGlobalScope(TenantScope::class)
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->exists(),
            'run' => AiAutopilotRun::withoutGlobalScope(TenantScope::class)
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->exists(),
            default => false,
        };
    }
}
