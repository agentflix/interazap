<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Chat ticket serialization.
 */
class ChatTicketResource extends BaseJsonResource
{
    /**
     * Mapa pré-carregado de mensagens citadas delegado ao ChatMessageResource.
     *
     * @var array<string, \Domain\Chat\Models\ChatMessage>
     */
    private array $quotedMap = [];

    /**
     * Definir o mapa de mensagens citadas pré-carregadas.
     *
     * @param  array<string, \Domain\Chat\Models\ChatMessage>  $map
     */
    public function withQuotedMap(array $map): static
    {
        $this->quotedMap = $map;

        return $this;
    }

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
            'contact_id' => $this->contact_id,
            'instance_id' => $this->instance_id,
            'assigned_to' => $this->assigned_to,
            'protocol' => $this->protocol,
            'channel' => $this->channel,
            'remote_jid' => $this->remote_jid,
            'phone' => $this->phone,
            'phone_e164' => $this->phone_e164,
            'push_name' => $this->push_name,
            'profile_picture_url' => $this->profile_picture_url,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'subject' => $this->subject,
            'is_group' => $this->is_group,
            'is_bot_active' => $this->is_bot_active,
            'queued_at' => $this->iso($this->created_at),
            'started_at' => $this->iso($this->started_at),
            'first_response_at' => $this->iso($this->first_response_at),
            'last_message_at' => $this->iso($this->last_message_at),
            'closed_at' => $this->iso($this->closed_at),
            'closed_by' => $this->closed_by,
            'close_reason' => $this->close_reason,
            'closed_mode' => $this->closed_mode,
            'wait_duration_seconds' => $this->computeWaitDuration(),
            'service_duration_seconds' => $this->computeServiceDuration(),
            'sentiment' => $this->sentiment,
            'sentiment_score' => $this->sentiment_score,
            'sentiment_updated_at' => $this->iso($this->sentiment_updated_at),
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'evaluation' => $this->when(
                $this->resource->relationLoaded('evaluation'),
                fn () => [
                    'has_evaluation' => $this->evaluation !== null,
                    'rating' => $this->evaluation?->rating,
                    'comment' => $this->evaluation?->comment,
                    'submitted_at' => $this->iso($this->evaluation?->submitted_at),
                ],
            ),
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact?->id,
                'name' => $this->contact?->name,
                'phone' => $this->contact?->phone,
                'whatsapp' => $this->contact?->whatsapp,
                'email' => $this->contact?->email,
                'profile_picture_url' => $this->contact?->avatar_url,
            ]),
            'assigned_user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'last_message' => $this->whenLoaded('latestMessage', fn () => (new ChatMessageResource($this->latestMessage))->withQuotedMap($this->quotedMap)->toArray($request)),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    /**
     * Compute wait duration in seconds (created_at → started_at).
     *
     * If the ticket has not started, uses current time as reference.
     *
     * @return int|null Duration in seconds or null if created_at is missing.
     */
    private function computeWaitDuration(): ?int
    {
        if (! $this->created_at) {
            return null;
        }

        $end = $this->started_at ?? now();

        return (int) $this->created_at->diffInSeconds($end);
    }

    /**
     * Compute service duration in seconds (started_at → closed_at).
     *
     * If the ticket is still open, uses current time as end reference.
     *
     * @return int|null Duration in seconds or null if not started.
     */
    private function computeServiceDuration(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        $end = $this->closed_at ?? now();

        return (int) $this->started_at->diffInSeconds($end);
    }
}
