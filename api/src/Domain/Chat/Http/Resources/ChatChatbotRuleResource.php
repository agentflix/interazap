<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Chatbot Rule serialization.
 */
final class ChatChatbotRuleResource extends BaseJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'trigger_text' => $this->trigger_text,
            'response_text' => $this->response_text,
            'description' => $this->response_text,
            'match_type' => 'contains',
            'patterns' => [$this->trigger_text],
            'actions' => [
                [
                    'type' => 'send_message',
                    'message' => $this->response_text,
                    'message_type' => '1',
                ],
            ],
            'instance_id' => null,
            'department_id' => null,
            'priority' => 0,
            'respect_business_hours' => true,
            'is_active' => $this->is_active,
            'is_welcome' => $this->is_welcome,
            'cooldown_seconds' => $this->cooldown_seconds,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
