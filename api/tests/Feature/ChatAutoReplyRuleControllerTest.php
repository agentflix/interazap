<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatAutoReplyRuleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_should_keep_only_one_welcome_rule_active_on_create(): void
    {
        $this->authenticateForAutoReplyRules();

        $firstRule = $this->postJson('/api/chat/auto-reply/rules', [
            'name' => 'Boas-vindas A',
            'trigger_text' => 'olá',
            'response_text' => 'Bem-vindo A',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 30,
        ])->assertCreated()->json('data');

        $secondRule = $this->postJson('/api/chat/auto-reply/rules', [
            'name' => 'Boas-vindas B',
            'trigger_text' => 'oi',
            'response_text' => 'Bem-vindo B',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 30,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('chat_auto_reply_rules', [
            'id' => $firstRule['id'],
            'is_welcome' => 0,
        ]);
        $this->assertDatabaseHas('chat_auto_reply_rules', [
            'id' => $secondRule['id'],
            'is_welcome' => 1,
        ]);
    }

    public function test_should_keep_only_one_welcome_rule_active_on_update(): void
    {
        $this->authenticateForAutoReplyRules();

        $firstRule = $this->postJson('/api/chat/auto-reply/rules', [
            'name' => 'Boas-vindas A',
            'trigger_text' => 'olá',
            'response_text' => 'Bem-vindo A',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 30,
        ])->assertCreated()->json('data');

        $secondRule = $this->postJson('/api/chat/auto-reply/rules', [
            'name' => 'Regra normal',
            'trigger_text' => 'menu',
            'response_text' => 'Menu principal',
            'is_active' => true,
            'is_welcome' => false,
            'cooldown_seconds' => 30,
        ])->assertCreated()->json('data');

        $this->putJson('/api/chat/auto-reply/rules/'.$secondRule['id'], [
            'name' => 'Boas-vindas B',
            'trigger_text' => 'menu',
            'response_text' => 'Bem-vindo B',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 30,
        ])->assertOk();

        $this->assertDatabaseHas('chat_auto_reply_rules', [
            'id' => $firstRule['id'],
            'is_welcome' => 0,
        ]);
        $this->assertDatabaseHas('chat_auto_reply_rules', [
            'id' => $secondRule['id'],
            'is_welcome' => 1,
        ]);
    }

    public function test_auto_reply_rule_crud_and_keyword_validation(): void
    {
        $this->authenticateForAutoReplyRules();

        $payload = [
            'name' => 'Auto Reply',
            'trigger_text' => 'hello',
            'response_text' => 'Hi there!',
            'is_active' => true,
            'cooldown_seconds' => 30,
        ];

        $created = $this->postJson('/api/chat/auto-reply/rules', $payload)
            ->assertCreated()
            ->json('data');

        $ruleId = $created['id'];

        $this->getJson('/api/chat/auto-reply/rules')
            ->assertOk()
            ->assertJsonFragment(['id' => $ruleId]);

        $this->getJson('/api/chat/auto-reply/rules/'.$ruleId)
            ->assertOk()
            ->assertJsonPath('data.id', $ruleId);

        $this->putJson('/api/chat/auto-reply/rules/'.$ruleId, [
            'name' => 'Auto Reply Updated',
            'trigger_text' => 'hi',
            'response_text' => 'Hello again',
            'is_active' => false,
            'cooldown_seconds' => 10,
        ])->assertOk();

        $this->assertDatabaseHas('chat_auto_reply_rules', [
            'id' => $ruleId,
            'name' => 'Auto Reply Updated',
            'trigger_text' => 'hi',
            'is_active' => 0,
            'cooldown_seconds' => 10,
        ]);

        $this->getJson('/api/chat/auto-reply/rules/validate-keyword?keyword=hi')
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->getJson('/api/chat/auto-reply/rules/validate-keyword?keyword=hi&rule_id='.$ruleId)
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $this->deleteJson('/api/chat/auto-reply/rules/'.$ruleId)
            ->assertNoContent();
    }

    private function authenticateForAutoReplyRules(): void
    {
        $user = AuthUser::factory()->create();
        $permissions = [
            'chat.auto_reply_rules.view',
            'chat.auto_reply_rules.create',
            'chat.auto_reply_rules.update',
            'chat.auto_reply_rules.delete',
        ];

        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $user->givePermissionTo($perm);
        }

        $this->actingAs($user, 'sanctum');
    }
}
