<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMTag;
use Illuminate\Support\Str;

/**
 * Tool to update contact tags using the BelongsToMany relationship.
 *
 * Security: Validates tenant_id to prevent cross-tenant access.
 */
class UpdateContactTagsTool implements AiToolInterface
{
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $contactId = $input->parameters['contact_id'] ?? null;
        $tagNames = $input->parameters['tags'] ?? [];
        $action = $input->parameters['action'] ?? 'add';
        $tenantId = $input->context['tenant_id'] ?? null;

        // Tenant isolation: only access contacts from the same tenant
        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->find($contactId);

        if (! $contact) {
            return ToolResultDTO::failure('Contact not found');
        }

        // Get current tag names for response
        $previousTags = $contact->tags()->pluck('name')->toArray();

        // Find or create tags by name within the tenant
        $tagIds = collect($tagNames)->map(function (string $tagName) use ($tenantId): string {
            $tag = CRMTag::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $tagName)
                ->first();

            if (! $tag) {
                $tag = CRMTag::create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenantId,
                    'name' => $tagName,
                ]);
            }

            return $tag->id;
        })->toArray();

        // Build pivot data with UUIDs for each tag
        $pivotData = collect($tagIds)->mapWithKeys(fn (string $tagId): array => [
            $tagId => [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
            ],
        ])->toArray();

        // Apply the action using the pivot relationship
        match ($action) {
            'replace' => $contact->tags()->sync($pivotData),
            'remove' => $contact->tags()->detach($tagIds),
            default => $contact->tags()->syncWithoutDetaching($pivotData),
        };

        // Get updated tag names
        $newTags = $contact->tags()->pluck('name')->toArray();

        return ToolResultDTO::success(
            message: "Tags updated successfully (action: {$action})",
            data: [
                'contact_id' => $contact->id,
                'previous_tags' => $previousTags,
                'new_tags' => $newTags,
                'action' => $action,
            ]
        );
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS;
    }

    public function getDescription(): string
    {
        return 'Updates the tags of a CRM contact. Can add, remove, or replace tags.';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array
    {
        return [
            'contact_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the contact',
            ],
            'tags' => [
                'type' => 'array',
                'required' => true,
                'description' => 'Array of tag strings',
                'items' => [
                    'type' => 'string',
                ],
            ],
            'action' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Action: add (default), remove, or replace',
                'enum' => ['add', 'remove', 'replace'],
            ],
        ];
    }
}
