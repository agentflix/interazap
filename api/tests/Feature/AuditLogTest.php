<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_audit_on_quick_answer_crud(): void
    {
        // Clear permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $user = AuthUser::factory()->create();

        // Give all CRUD permissions as required by ChatQuickAnswerPolicy AND ChatQuickAnswerRequest
        $permNames = [
            'chat.quicks.manage',  // Required by ChatQuickAnswerRequest::authorize()
            'chat.quick_answers.create',  // Required by ChatQuickAnswerPolicy::create()
            'chat.quick_answers.update',  // Required by ChatQuickAnswerPolicy::update()
            'chat.quick_answers.delete',  // Required by ChatQuickAnswerPolicy::delete()
        ];
        foreach ($permNames as $permName) {
            $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(
                ['name' => $permName, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $user->givePermissionTo($perm);
        }

        Sanctum::actingAs($user, ['*']);

        $res = $this->postJson('/api/chat/quick-answers', [
            'name' => 'Oi',
            'content' => 'Olá, como posso ajudar?',
        ]);

        $res->assertCreated();

        $qaId = $res->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'chat.quick_answers.created',
            'auditable_id' => $qaId,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->putJson("/api/chat/quick-answers/{$qaId}", [
            'name' => 'Oi!',
            'content' => 'Oi, tudo bem?',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'chat.quick_answers.updated',
            'auditable_id' => $qaId,
        ]);

        $this->deleteJson("/api/chat/quick-answers/{$qaId}")
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'chat.quick_answers.deleted',
            'auditable_id' => $qaId,
        ]);
    }
}
