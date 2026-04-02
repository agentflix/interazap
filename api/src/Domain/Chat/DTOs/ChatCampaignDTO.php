<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Chat Campaign.
 *
 * @readonly
 */
final readonly class ChatCampaignDTO
{
    /**
     * @param  string  $name  Campaign name.
     * @param  string  $status  Status (draft, scheduled, etc).
     * @param  string|null  $message  Message content.
     * @param  array<string, mixed>|null  $filterCriteria  Contact filter criteria.
     * @param  string|null  $instanceId  WhatsApp instance ID.
     * @param  string|null  $scheduledAt  Scheduled dispatch datetime.
     * @param  array<string, mixed>|null  $metadata  Additional campaign metadata.
     */
    public function __construct(
        public string $name,
        public string $status = 'draft',
        public ?string $message = null,
        public ?array $filterCriteria = null,
        public ?string $instanceId = null,
        public ?string $scheduledAt = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray([
            'name' => $request->input('name'),
            'status' => $request->input('status'),
            'message' => $request->input('message'),
            'filter_criteria' => $request->input('filter_criteria'),
            'instance_id' => $request->input('instance_id'),
            'scheduled_at' => $request->input('scheduled_at'),
            'metadata' => $request->input('metadata'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            status: (string) ($data['status'] ?? 'draft'),
            message: $data['message'] ?? null,
            filterCriteria: isset($data['filter_criteria']) && is_array($data['filter_criteria']) ? $data['filter_criteria'] : null,
            instanceId: $data['instance_id'] ?? null,
            scheduledAt: $data['scheduled_at'] ?? null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
            'filter_criteria' => $this->filterCriteria,
            'instance_id' => $this->instanceId,
            'scheduled_at' => $this->scheduledAt,
            'metadata' => $this->metadata,
        ];
    }
}
