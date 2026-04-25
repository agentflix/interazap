<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

describe('Input Validation Security', function (): void {
    beforeEach(function (): void {
        $this->user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
        ]);

        // Grant necessary permissions for tests
        $permissions = ['chat.tickets.view', 'chat.tickets.update', 'chat.transmission_lists.manage'];
        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $this->user->givePermissionTo($perm);
        }

        Sanctum::actingAs($this->user);
    });

    it('ChatTicketCloseRequest validates reason max length', function (): void {
        $instance = ChatInstance::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'instance_id' => $instance->id,
        ]);

        // Should fail with reason too long
        $longReason = str_repeat('a', 501);
        $response = $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'reason' => $longReason,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    });

    it('ChatTicketCloseRequest accepts valid reason', function (): void {
        $instance = ChatInstance::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'instance_id' => $instance->id,
            'status' => 'open',
        ]);

        $response = $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'reason' => 'Customer issue resolved.',
        ]);

        $response->assertSuccessful();
    });

    it('ChatTransmissionListPreviewRequest requires message', function (): void {
        $response = $this->postJson('/api/chat/transmission-lists/preview', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    });

    it('ChatTransmissionListPreviewRequest validates message max length', function (): void {
        $longMessage = str_repeat('a', 4097);
        $response = $this->postJson('/api/chat/transmission-lists/preview', [
            'message' => $longMessage,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    });

    it('ChatTransmissionListAudienceRequest validates criteria structure', function (): void {
        $response = $this->postJson('/api/chat/transmission-lists/audience', [
            'criteria' => [
                'tags' => ['invalid-uuid'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['criteria.tags.0']);
    });

    it('AiNotificationMarkAsReadRequest requires ids array', function (): void {
        $response = $this->postJson('/api/ai/notifications/mark-read', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    });

    it('AiNotificationMarkAsReadRequest validates ids are uuids', function (): void {
        $response = $this->postJson('/api/ai/notifications/mark-read', [
            'ids' => ['not-a-uuid', 'also-not-uuid'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids.0', 'ids.1']);
    });

    it('AiNotificationMarkAsReadRequest limits max ids', function (): void {
        $ids = array_fill(0, 101, fake()->uuid());
        $response = $this->postJson('/api/ai/notifications/mark-read', [
            'ids' => $ids,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    });
});
