<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource público para PlatformLead.
 *
 * Não expõe dados pessoais (email/phone) na resposta —
 * a landing só precisa confirmar o recebimento.
 */
final class PlatformLeadResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source' => $this->source,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
