<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatAutoReplyRule;
use Domain\Chat\Models\ChatQuickAnswer;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTransmissionList;
use Domain\Chat\Policies\ChatAutoReplyRulePolicy;
use Domain\Chat\Policies\ChatMessagePolicy;
use Domain\Chat\Policies\ChatQuickAnswerPolicy;
use Domain\Chat\Policies\ChatTransmissionListPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatPolicyCoverageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function grantPermissions(AuthUser $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $user->givePermissionTo($perm);
        }
    }

    public function test_transmission_list_policy_permissions_and_tenant_scope(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $this->grantPermissions($user, [
            'chat.transmission_lists.view',
            'chat.transmission_lists.create',
            'chat.transmission_lists.update',
            'chat.transmission_lists.delete',
        ]);

        $transmissionList = ChatTransmissionList::factory()->create(['tenant_id' => $user->tenant_id]);
        $foreignTransmissionList = ChatTransmissionList::factory()->create(['tenant_id' => $otherUser->tenant_id]);

        $policy = new ChatTransmissionListPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $transmissionList));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $transmissionList));
        $this->assertTrue($policy->delete($user, $transmissionList));

        $this->assertFalse($policy->view($user, $foreignTransmissionList));
        $this->assertFalse($policy->update($user, $foreignTransmissionList));
        $this->assertFalse($policy->delete($user, $foreignTransmissionList));
    }

    public function test_quick_answer_policy_permissions_and_tenant_scope(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $this->grantPermissions($user, [
            'chat.quick_answers.view',
            'chat.quick_answers.create',
            'chat.quick_answers.update',
            'chat.quick_answers.delete',
        ]);

        $qa = ChatQuickAnswer::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Resposta rápida',
            'shortcut' => '/ola',
            'content' => 'Olá! Como posso ajudar?',
            'category' => 'saudacao',
            'is_active' => true,
        ]);
        $foreignQa = ChatQuickAnswer::query()->create([
            'tenant_id' => $otherUser->tenant_id,
            'name' => 'Resposta externa',
            'shortcut' => '/externa',
            'content' => 'Olá de outro tenant',
            'category' => 'saudacao',
            'is_active' => true,
        ]);

        $policy = new ChatQuickAnswerPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $qa));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $qa));
        $this->assertTrue($policy->delete($user, $qa));

        $this->assertFalse($policy->view($user, $foreignQa));
        $this->assertFalse($policy->update($user, $foreignQa));
        $this->assertFalse($policy->delete($user, $foreignQa));
    }

    public function test_auto_reply_rule_policy_permissions_and_tenant_scope(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $this->grantPermissions($user, [
            'chat.auto_reply_rules.view',
            'chat.auto_reply_rules.create',
            'chat.auto_reply_rules.update',
            'chat.auto_reply_rules.delete',
        ]);

        $rule = ChatAutoReplyRule::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Boas-vindas',
            'trigger_text' => 'oi',
            'response_text' => 'Olá! Sou o bot.',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 60,
        ]);
        $foreignRule = ChatAutoReplyRule::query()->create([
            'tenant_id' => $otherUser->tenant_id,
            'name' => 'Outro bot',
            'trigger_text' => 'oi',
            'response_text' => 'Olá de outro tenant.',
            'is_active' => true,
            'is_welcome' => false,
            'cooldown_seconds' => 60,
        ]);

        $policy = new ChatAutoReplyRulePolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $rule));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $rule));
        $this->assertTrue($policy->delete($user, $rule));

        $this->assertFalse($policy->view($user, $foreignRule));
        $this->assertFalse($policy->update($user, $foreignRule));
        $this->assertFalse($policy->delete($user, $foreignRule));
    }

    public function test_message_policy_permissions_and_tenant_scope(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $this->grantPermissions($user, [
            'chat.messages.view',
            'chat.messages.create',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();
        $foreignTicket = ChatTicket::factory()->forTenant($otherUser->tenant_id)->create();

        $policy = new ChatMessagePolicy;

        $this->assertTrue($policy->viewAny($user, $ticket));
        $this->assertTrue($policy->create($user, $ticket));
        $this->assertFalse($policy->viewAny($user, $foreignTicket));
        $this->assertFalse($policy->create($user, $foreignTicket));
    }
}
