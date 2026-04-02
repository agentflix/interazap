<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatQuickAnswer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatQuickAnswerControllerListTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_show_and_all_quick_answers(): void
    {
        $user = AuthUser::factory()->create();
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.quick_answers.view', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()]
        );
        $user->givePermissionTo($permission);

        $active = ChatQuickAnswer::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Boas vindas',
            'shortcut' => '/ola',
            'content' => 'Olá!',
            'category' => 'greeting',
            'is_active' => true,
        ]);

        ChatQuickAnswer::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Follow-up',
            'shortcut' => '/follow',
            'content' => 'Posso ajudar em algo mais?',
            'category' => 'support',
            'is_active' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/chat/quick-answers')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->id]);

        $this->getJson('/api/chat/quick-answers/'.$active->id)
            ->assertOk()
            ->assertJsonPath('data.id', $active->id);

        $this->getJson('/api/chat/quick-answers/all')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $active->id]);
    }
}
