<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatMessageTemplateControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_and_create_message_templates(): void
    {
        $user = AuthUser::factory()->create();

        $perm = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.templates.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($perm);

        ChatMessageTemplate::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Welcome',
            'content' => 'Olá',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/chat/message-templates')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Welcome']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/chat/message-templates', [
                'name' => 'Follow up',
                'content' => 'Olá',
                'language' => 'pt_BR',
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Follow up']);
    }
}
