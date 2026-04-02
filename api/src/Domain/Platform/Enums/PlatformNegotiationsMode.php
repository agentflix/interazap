<?php

declare(strict_types=1);

namespace Domain\Platform\Enums;

/**
 * Modos de limite de negociações.
 */
enum PlatformNegotiationsMode: string
{
    case UNLIMITED = 'UNLIMITED';
    case LIMITED = 'LIMITED';
}
