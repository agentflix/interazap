<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\SendMessageTool;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class SendMessageToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SendMessageTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = $this->app->make(SendMessageTool::class);
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('send_message');
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

        expect($params)->toHaveKeys(['ticket_id', 'content', 'type', 'file_url', 'file_name', 'mime_type', 'file_size']);
        expect($params['ticket_id']['required'])->toBeTrue();
        expect($params['content']['required'])->toBeTrue();
        expect($params['type']['required'])->toBeFalse();
        expect($params['file_url']['required'])->toBeFalse();
        expect($params['file_name']['required'])->toBeFalse();
        expect($params['mime_type']['required'])->toBeFalse();
        expect($params['file_size']['required'])->toBeFalse();
    }

    public function test_it_sends_text_message_successfully(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);

        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => 'Hello! How can I help you today?',
                'type' => 'text',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('message_id');

        $message = \Domain\Chat\Models\ChatMessage::query()->find($result->data['message_id']);
        expect($message)->not->toBeNull();
        expect($message->content)->toBe('Hello! How can I help you today?');
        expect($message->direction)->toBe('outgoing');
        expect($message->source)->toBe('ai');
    }

    public function test_it_fails_when_ticket_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => '00000000-0000-0000-0000-000000099999',
                'content' => 'Hello!',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    public function test_it_fails_when_content_is_empty(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => '',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('cannot be empty');
    }

    public function test_it_fails_when_ticket_is_closed(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'closed',
        ]);

        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => 'Hello!',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('closed');
    }

    public function test_it_preserves_file_metadata_when_provided(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);

        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => 'Arquivo enviado',
                'type' => 'document',
                'file_url' => 'https://example.com/files/recibo.pdf',
                'file_name' => 'recibo.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => '2048',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();

        $message = \Domain\Chat\Models\ChatMessage::query()->find($result->data['message_id']);
        expect($message)->not->toBeNull();
        expect($message?->file_url)->toBe('https://example.com/files/recibo.pdf');
        expect($message?->file_name)->toBe('recibo.pdf');
        expect($message?->mime_type)->toBe('application/pdf');
        expect($message?->file_size)->toBe(2048);
    }

    public function test_it_sets_file_size_to_null_when_non_numeric_value_is_provided(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);

        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => 'Arquivo inválido',
                'type' => 'document',
                'file_url' => 'https://example.com/files/invalid.pdf',
                'file_name' => 'invalid.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 'abc',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();

        $message = \Domain\Chat\Models\ChatMessage::query()->find($result->data['message_id']);
        expect($message)->not->toBeNull();
        expect($message?->file_size)->toBeNull();
    }

    public function test_it_sets_file_size_to_null_when_negative_value_is_provided(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);

        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => 'Arquivo com tamanho negativo',
                'type' => 'document',
                'file_url' => 'https://example.com/files/negative.pdf',
                'file_name' => 'negative.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => -1,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();

        $message = \Domain\Chat\Models\ChatMessage::query()->find($result->data['message_id']);
        expect($message)->not->toBeNull();
        expect($message?->file_size)->toBeNull();
    }
}
