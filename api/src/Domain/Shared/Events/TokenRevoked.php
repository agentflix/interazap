<?php

declare(strict_types=1);

namespace Domain\Shared\Events;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando um token de acesso é revogado.
 *
 * @category Events
 */
final class TokenRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AuthUser $user,
        public readonly ?string $tokenId = null,
    ) {}
}
