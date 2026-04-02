<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Policies\ChatTicketPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTicketPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_policy_permissions_and_tenant_scope(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $permissions = [
            'chat.tickets.view',
            'chat.tickets.create',
            'chat.tickets.update',
            'chat.tickets.transfer',
        ];

        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $user->givePermissionTo($perm);
        }

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();
        $foreignTicket = ChatTicket::factory()->forTenant($otherUser->tenant_id)->create();

        $policy = new ChatTicketPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $ticket));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $ticket));
        $this->assertTrue($policy->transfer($user, $ticket));

        $this->assertFalse($policy->view($user, $foreignTicket));
        $this->assertFalse($policy->update($user, $foreignTicket));
        $this->assertFalse($policy->transfer($user, $foreignTicket));
    }
}
