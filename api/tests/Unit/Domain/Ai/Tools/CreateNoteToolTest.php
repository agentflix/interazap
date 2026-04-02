<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\CreateNoteTool;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class CreateNoteToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CreateNoteTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new CreateNoteTool;
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('create_note');
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

        expect($params)->toHaveKeys(['entity_type', 'entity_id', 'content']);
        expect($params['entity_type']['required'])->toBeTrue();
        expect($params['entity_id']['required'])->toBeTrue();
        expect($params['content']['required'])->toBeTrue();
    }

    public function test_it_creates_note_for_contact(): void
    {
        $contact = CRMContact::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'create_note',
            parameters: [
                'entity_type' => 'contact',
                'entity_id' => $contact->id,
                'content' => 'Test note from AI',
            ],
            context: ['tenant_id' => $contact->tenant_id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('note_id');

        $note = \Domain\CRM\Models\CRMNote::query()->find($result->data['note_id']);
        expect($note)->not->toBeNull();
        expect($note->content)->toBe('Test note from AI');
        expect($note->entity_type)->toBe('contact');
        expect($note->entity_id)->toBe($contact->id);
    }

    public function test_it_creates_note_for_negotiation(): void
    {
        $negotiation = CRMNegotiation::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'create_note',
            parameters: [
                'entity_type' => 'negotiation',
                'entity_id' => $negotiation->id,
                'content' => 'Negotiation note from AI',
            ],
            context: ['tenant_id' => $negotiation->tenant_id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('note_id');
    }

    public function test_it_fails_when_entity_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'create_note',
            parameters: [
                'entity_type' => 'contact',
                'entity_id' => '00000000-0000-0000-0000-000000099999',
                'content' => 'Test note',
            ],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    public function test_it_fails_for_invalid_entity_type(): void
    {
        $input = new ToolInputDTO(
            toolName: 'create_note',
            parameters: [
                'entity_type' => 'invalid_type',
                'entity_id' => '00000000-0000-0000-0000-000000000001',
                'content' => 'Test note',
            ],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('Invalid entity type');
    }

    public function test_it_fails_when_content_is_empty(): void
    {
        $contact = CRMContact::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'create_note',
            parameters: [
                'entity_type' => 'contact',
                'entity_id' => $contact->id,
                'content' => '',
            ],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('cannot be empty');
    }
}
