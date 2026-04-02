<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\GetContactInfoTool;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class GetContactInfoToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GetContactInfoTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new GetContactInfoTool;
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('get_contact_info');
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

        expect($params)->toHaveKeys(['contact_id']);
        expect($params['contact_id']['required'])->toBeTrue();
    }

    public function test_it_retrieves_contact_info_successfully(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '5511999999999',
            'custom_fields' => ['city' => 'São Paulo'],
        ]);

        $input = new ToolInputDTO(
            toolName: 'get_contact_info',
            parameters: [
                'contact_id' => $contact->id,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data['contact']['name'])->toBe('John Doe');
        expect($result->data['contact']['email'])->toBe('john@example.com');
        expect($result->data['contact']['city'])->toBe('São Paulo');
        expect($result->data['contact']['phone'])->toBe('5511999999999');
    }

    public function test_it_fails_when_contact_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'get_contact_info',
            parameters: [
                'contact_id' => '00000000-0000-0000-0000-000000099999',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }
}
