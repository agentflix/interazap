<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers\Internal;

use Domain\Chat\Actions\LookupInstanceByWabaIdAction;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controller interno consumido pelo Gateway para resolver identificadores de
 * canais Meta a partir de IDs externos (WABA, etc.) durante a normalização
 * de webhooks que não trazem `phone_number_id`.
 *
 * Protegido pelo middleware `gateway.secret` (mesmo padrão do
 * {@see \Domain\Chat\Http\Controllers\ChatWindowController::lookupByPhoneNumber()}).
 *
 * @category Controllers
 */
final class InternalChannelLookupController extends BaseController
{
    public function __construct(
        private readonly LookupInstanceByWabaIdAction $lookupAction,
    ) {}

    /**
     * Resolve waba_id → ChatInstance (tenant + instance).
     * GET /api/internal/chat/instances/by-waba/{wabaId}
     *
     * @param  string  $wabaId  WhatsApp Business Account ID da Meta.
     */
    public function byWaba(string $wabaId): JsonResponse
    {
        $result = $this->lookupAction->execute($wabaId);

        if ($result === null) {
            return $this->error('Instance not found', 404);
        }

        return $this->success($result);
    }
}
