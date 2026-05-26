<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de Template de Mensagem.
 *
 * Transforma a entidade ChatMessageTemplate no formato da API,
 * expondo campos como status de aprovação Meta, idioma e componentes estruturados.
 */
final class ChatMessageTemplateResource extends BaseJsonResource
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
            'name' => $this->name,
            'shortcut' => $this->shortcut,
            'content' => $this->content,
            'category' => $this->category,
            'is_active' => $this->is_active,
            'provider' => $this->provider,
            'chat_instance_id' => $this->chat_instance_id,
            'external_id' => $this->external_id,
            'language' => $this->language,
            'status' => $this->status,
            'rejected_reason' => $this->rejected_reason,
            'components' => $this->components_json,
            'last_synced_at' => $this->iso($this->last_synced_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
