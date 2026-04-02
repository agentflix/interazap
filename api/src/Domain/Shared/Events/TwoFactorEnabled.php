<?php

declare(strict_types=1);

namespace Domain\Shared\Events;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando 2FA é habilitado para um usuário.
 *
 * @category Events
 */
final class TwoFactorEnabled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AuthUser $user,
    ) {}
}
