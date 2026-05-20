<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\NotifySellerTool;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class NotifySellerToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private NotifySellerTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = app(NotifySellerTool::class);
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('notify_seller');
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

        expect($params)->toHaveKeys(['seller_id', 'message', 'reason', 'channel', 'priority']);
        expect($params['seller_id']['required'])->toBeFalse();
        expect($params['message']['required'])->toBeTrue();
        expect($params['reason']['required'])->toBeTrue();
        expect($params['channel']['required'])->toBeFalse();
        expect($params['priority']['required'])->toBeFalse();
    }

    public function test_it_queues_notification_successfully(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $seller = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        $input = new ToolInputDTO(
            toolName: 'notify_seller',
            parameters: [
                'seller_id' => (string) $seller->id,
                'message' => 'New lead assigned to you!',
                'reason' => 'hot_lead',
                'channel' => 'email',
                'priority' => 'high',
            ],
            context: ['tenant_id' => (string) $tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->message)->toContain('queued');
    }

    public function test_it_fails_when_message_is_empty(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'notify_seller',
            parameters: [
                'seller_id' => '00000000-0000-0000-0000-000000000001',
                'message' => '',
                'reason' => 'test',
            ],
            context: ['tenant_id' => (string) $tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('cannot be empty');
    }

    public function test_it_resolves_seller_by_name_when_uuid_is_not_available(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $seller = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Rosa Comercial',
        ]);

        $input = new ToolInputDTO(
            toolName: 'notify_seller',
            parameters: [
                'seller' => 'Rosa Comercial',
                'message' => 'Test message',
                'reason' => 'hot_lead',
            ],
            context: ['tenant_id' => (string) $tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['seller_id'])->toBe((string) $seller->id);
    }

    public function test_it_returns_recoverable_failure_when_seller_cannot_be_resolved(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'notify_seller',
            parameters: [
                'seller' => 'Pessoa Inexistente',
                'message' => 'Test message',
                'reason' => 'hot_lead',
            ],
            context: ['tenant_id' => (string) $tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->data['error_code'])->toBe('seller_not_found');
        expect($result->data['recoverable'])->toBeTrue();
    }
}
