<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatAutoReplyRuleActions;
use Domain\Chat\DTOs\ChatAutoReplyRuleDTO;
use Domain\Chat\Models\ChatAutoReplyRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatAutoReplyRuleActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_create_update_find_and_delete_rules(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;
        $otherTenantId = (string) AuthUser::factory()->create()->tenant_id;

        $actions = app(ChatAutoReplyRuleActions::class);

        $created = $actions->create($tenantId, new ChatAutoReplyRuleDTO(
            name: 'Greeting',
            triggerText: 'hello',
            responseText: 'Hello there!',
            isActive: true,
            cooldownSeconds: 10,
        ));

        ChatAutoReplyRule::query()->create([
            'tenant_id' => $otherTenantId,
            'name' => 'Other',
            'trigger_text' => 'other',
            'response_text' => 'Other response',
            'is_active' => true,
            'cooldown_seconds' => 0,
        ]);

        $this->assertDatabaseHas('chat_auto_reply_rules', [
            'id' => $created->id,
            'tenant_id' => $tenantId,
            'trigger_text' => 'hello',
        ]);

        $list = $actions->list($tenantId);
        $this->assertSame(1, $list->total());

        $found = $actions->find($tenantId, $created->id);
        $this->assertSame($created->id, $found->id);

        $updated = $actions->update($tenantId, $created->id, new ChatAutoReplyRuleDTO(
            name: 'Greeting Updated',
            triggerText: 'hi',
            responseText: 'Hi there!',
            isActive: false,
            cooldownSeconds: 25,
        ));

        $this->assertSame('Greeting Updated', $updated->name);
        $this->assertDatabaseHas('chat_auto_reply_rules', [
            'id' => $created->id,
            'trigger_text' => 'hi',
            'response_text' => 'Hi there!',
            'is_active' => 0,
            'cooldown_seconds' => 25,
        ]);

        $actions->delete($tenantId, $created->id);
        $this->assertDatabaseMissing('chat_auto_reply_rules', [
            'id' => $created->id,
        ]);

        $this->expectException(ModelNotFoundException::class);
        $actions->find($tenantId, $created->id);
    }

    public function test_keyword_availability_checks_normalization_and_ignore(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;

        $rule = ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Saudacao',
            'trigger_text' => 'Ola Mundo',
            'response_text' => 'Oi!',
            'is_active' => true,
            'cooldown_seconds' => 0,
        ]);

        $actions = app(ChatAutoReplyRuleActions::class);

        $this->assertFalse($actions->isKeywordAvailable($tenantId, 'Ola    Mundo'));
        $this->assertTrue($actions->isKeywordAvailable($tenantId, 'Ola    Mundo', ignoreRuleId: $rule->id));
        $this->assertTrue($actions->isKeywordAvailable($tenantId, 'novo'));
        $this->assertFalse($actions->isKeywordAvailable($tenantId, '   '));
    }
}
