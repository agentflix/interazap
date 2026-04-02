<?php

declare(strict_types=1);

namespace Domain\Configuration\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationOpeningHour;

/**
 * Policy for Configuration Opening Hours.
 */
final class ConfigurationOpeningHourPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function view(AuthUser $user, ConfigurationOpeningHour $openingHour): bool
    {
        return $openingHour->tenant_id === $user->tenant_id;
    }

    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function update(AuthUser $user, ConfigurationOpeningHour $openingHour): bool
    {
        return $openingHour->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, ConfigurationOpeningHour $openingHour): bool
    {
        return $openingHour->tenant_id === $user->tenant_id;
    }
}
