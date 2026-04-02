<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatChatbotRuleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_chatbot_rule_crud_and_keyword_validation(): void
    {
        $user = AuthUser::factory()->create();
        $permissions = [
            'chat.chatbot_rules.view',
            'chat.chatbot_rules.create',
            'chat.chatbot_rules.update',
            'chat.chatbot_rules.delete',
        ];

        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $user->givePermissionTo($perm);
        }

        $this->actingAs($user, 'sanctum');

        $payload = [
            'name' => 'Auto Reply',
            'trigger_text' => 'hello',
            'response_text' => 'Hi there!',
            'is_active' => true,
            'cooldown_seconds' => 30,
        ];

        $created = $this->postJson('/api/chat/chatbot/rules', $payload)
            ->assertCreated()
            ->json('data');

        $ruleId = $created['id'];

        $this->getJson('/api/chat/chatbot/rules')
            ->assertOk()
            ->assertJsonFragment(['id' => $ruleId]);

        $this->getJson('/api/chat/chatbot/rules/'.$ruleId)
            ->assertOk()
            ->assertJsonPath('data.id', $ruleId);

        $this->putJson('/api/chat/chatbot/rules/'.$ruleId, [
            'name' => 'Auto Reply Updated',
            'trigger_text' => 'hi',
            'response_text' => 'Hello again',
            'is_active' => false,
            'cooldown_seconds' => 10,
        ])->assertOk();

        $this->assertDatabaseHas('chat_chatbot_rules', [
            'id' => $ruleId,
            'name' => 'Auto Reply Updated',
            'trigger_text' => 'hi',
            'is_active' => 0,
            'cooldown_seconds' => 10,
        ]);

        $this->getJson('/api/chat/chatbot/rules/validate-keyword?keyword=hi')
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->getJson('/api/chat/chatbot/rules/validate-keyword?keyword=hi&rule_id='.$ruleId)
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $this->deleteJson('/api/chat/chatbot/rules/'.$ruleId)
            ->assertNoContent();
    }
}
