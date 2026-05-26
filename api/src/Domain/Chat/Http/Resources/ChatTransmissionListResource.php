<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de Lista de Transmissão.
 *
 * Transforma a entidade ChatTransmissionList no formato da API,
 * expondo critérios de filtragem, status de envio e agendamento.
 */
final class ChatTransmissionListResource extends BaseJsonResource
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
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
            'filter_criteria' => $this->filter_criteria,
            'instance_id' => $this->instance_id,
            'scheduled_at' => $this->iso($this->scheduled_at),
            'metadata' => $this->metadata,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
