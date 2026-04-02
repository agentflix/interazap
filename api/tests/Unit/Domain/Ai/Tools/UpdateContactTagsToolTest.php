<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\UpdateContactTagsTool;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class UpdateContactTagsToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UpdateContactTagsTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new UpdateContactTagsTool;
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('update_contact_tags');
    }

    public function test_it_has_description(): void
    {
        expect($this->tool->getDescription())
            ->toBeString()
            ->not->toBeEmpty();
    }

    public function test_it_has_required_parameters(): void
    {
        $params = $this->tool->getParameters();

        expect($params)->toHaveKeys(['contact_id', 'tags', 'action']);
        expect($params['contact_id']['required'])->toBeTrue();
        expect($params['tags']['required'])->toBeTrue();
        expect($params['action']['required'])->toBeFalse();
    }

    public function test_it_adds_tags_successfully(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create existing tag and attach to contact with UUID
        $existingTag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'existing',
        ]);
        $contact->tags()->attach($existingTag->id, [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'update_contact_tags',
            parameters: [
                'contact_id' => $contact->id,
                'tags' => ['new_tag', 'another_tag'],
                'action' => 'add',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();

        $tagNames = $contact->tags()->pluck('name')->toArray();
        expect($tagNames)->toContain('existing');
        expect($tagNames)->toContain('new_tag');
        expect($tagNames)->toContain('another_tag');
    }

    public function test_it_replaces_tags_when_action_is_replace(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create existing tag and attach with UUID
        $oldTag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'old_tag',
        ]);
        $contact->tags()->attach($oldTag->id, [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'update_contact_tags',
            parameters: [
                'contact_id' => $contact->id,
                'tags' => ['brand_new'],
                'action' => 'replace',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();

        $tagNames = $contact->tags()->pluck('name')->toArray();
        expect($tagNames)->toBe(['brand_new']);
    }

    public function test_it_removes_tags_when_action_is_remove(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create tags and attach both with UUIDs
        $keepTag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'keep',
        ]);
        $removeTag = CRMTag::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'remove_me',
        ]);
        $contact->tags()->attach([
            $keepTag->id => ['id' => (string) Str::orderedUuid(), 'tenant_id' => $this->tenant->id],
            $removeTag->id => ['id' => (string) Str::orderedUuid(), 'tenant_id' => $this->tenant->id],
        ]);

        $input = new ToolInputDTO(
            toolName: 'update_contact_tags',
            parameters: [
                'contact_id' => $contact->id,
                'tags' => ['remove_me'],
                'action' => 'remove',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();

        $tagNames = $contact->tags()->pluck('name')->toArray();
        expect($tagNames)->toContain('keep');
        expect($tagNames)->not->toContain('remove_me');
    }

    public function test_it_fails_when_contact_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'update_contact_tags',
            parameters: [
                'contact_id' => '00000000-0000-0000-0000-000000099999',
                'tags' => ['tag'],
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }
}
