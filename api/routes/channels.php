<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('tenant.{tenantId}', fn (AuthUser $user, string $tenantId): bool => (string) $user->tenant_id === $tenantId);

Broadcast::channel('ticket.{ticketId}', fn (AuthUser $user, string $ticketId): bool => ChatTicket::query()
    ->where('id', $ticketId)
    ->where('tenant_id', $user->tenant_id)
    ->exists());
