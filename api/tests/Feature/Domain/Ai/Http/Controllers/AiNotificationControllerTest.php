<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Http\Controllers;

use Domain\Ai\Models\AiSellerNotification;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @group ai
 * @group controllers
 */
class AiNotificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $this->user->givePermissionTo($permission);
    }

    public function test_index_returns_user_notifications(): void
    {
        Sanctum::actingAs($this->user);

        // Create notifications for this user
        AiSellerNotification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/ai/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'message',
                        'reason',
                        'reason_label',
                        'channel',
                        'priority',
                        'is_read',
                        'created_at',
                    ],
                ],
            ]);

        expect(count($response->json('data')))->toBe(3);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/notifications');

        $response->assertUnauthorized();
    }

    public function test_index_filters_unread_only(): void
    {
        Sanctum::actingAs($this->user);

        // Create read notifications
        AiSellerNotification::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
            'delivered_at' => now(),
        ]);

        // Create unread notifications
        AiSellerNotification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
            'delivered_at' => null,
        ]);

        $response = $this->getJson('/api/ai/notifications?unread_only=true');

        $response->assertOk();
        expect(count($response->json('data')))->toBe(3);
    }

    public function test_show_returns_single_notification(): void
    {
        Sanctum::actingAs($this->user);

        $notification = AiSellerNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
            'message' => 'Test notification',
        ]);

        $response = $this->getJson("/api/ai/notifications/{$notification->id}");

        $response->assertOk();
        expect($response->json('data.id'))->toBe($notification->id);
        expect($response->json('data.message'))->toBe('Test notification');
    }

    public function test_show_returns_404_for_other_user_notification(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $notification = AiSellerNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/ai/notifications/{$notification->id}");

        $response->assertNotFound();
    }

    public function test_mark_as_read_marks_specific_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $notifications = AiSellerNotification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
            'delivered_at' => null,
        ]);

        $idsToMark = [$notifications[0]->id, $notifications[1]->id];

        $response = $this->postJson('/api/ai/notifications/mark-read', [
            'ids' => $idsToMark,
        ]);

        $response->assertOk();
        expect($response->json('updated_count'))->toBe(2);

        // Verify they are marked
        $this->assertDatabaseHas('ai_seller_notifications', [
            'id' => $notifications[0]->id,
        ]);

        $notifications[0]->refresh();
        $notifications[2]->refresh();

        expect($notifications[0]->delivered_at)->not->toBeNull();
        expect($notifications[2]->delivered_at)->toBeNull();
    }

    public function test_mark_as_read_marks_all_by_ids(): void
    {
        Sanctum::actingAs($this->user);

        $notifications = AiSellerNotification::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
            'delivered_at' => null,
        ]);

        $response = $this->postJson('/api/ai/notifications/mark-read', [
            'ids' => $notifications->pluck('id')->toArray(),
        ]);

        $response->assertOk();
        expect($response->json('updated_count'))->toBe(5);
    }

    public function test_unread_count_returns_correct_count(): void
    {
        Sanctum::actingAs($this->user);

        // Create mix of read and unread
        AiSellerNotification::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
            'delivered_at' => now(),
        ]);
        AiSellerNotification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
            'delivered_at' => null,
        ]);

        $response = $this->getJson('/api/ai/notifications/unread-count');

        $response->assertOk();
        expect($response->json('data.unread_count'))->toBe(3);
    }

    public function test_index_isolates_by_tenant(): void
    {
        Sanctum::actingAs($this->user);

        // Create notifications for this tenant
        AiSellerNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seller_id' => $this->user->id,
        ]);

        // Create notifications for another tenant (same user id would be different user)
        $otherTenant = PlatformTenant::factory()->create();
        $otherUser = AuthUser::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        AiSellerNotification::factory()->count(10)->create([
            'tenant_id' => $otherTenant->id,
            'seller_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/ai/notifications');

        $response->assertOk();
        expect(count($response->json('data')))->toBe(1);
    }
}
