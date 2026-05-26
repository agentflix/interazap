<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de Rotação de Atendentes.
 *
 * Transforma a entidade ChatUserRotation no formato da API,
 * expondo o peso de distribuição e o status de ativação do agente na fila.
 */
final class ChatUserRotationResource extends BaseJsonResource
{
    /**
     * Transforma a entidade no array de resposta da API.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'weight' => $this->weight,
            'is_active' => $this->is_active,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
