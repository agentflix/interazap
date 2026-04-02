<?php

declare(strict_types=1);

namespace Domain\Platform\Enums;

/**
 * Modos de acesso aos relatórios.
 */
enum PlatformReportsMode: string
{
    case BASIC = 'BASIC';
    case ADVANCED = 'ADVANCED';
    case FULL = 'FULL';
}
