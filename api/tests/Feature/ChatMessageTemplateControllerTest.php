<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatMessageTemplateControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_and_create_message_templates(): void
    {
        $user = AuthUser::factory()->create();

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
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Follow up']);
    }
}
