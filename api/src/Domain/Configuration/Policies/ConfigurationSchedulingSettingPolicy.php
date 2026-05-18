<?php

declare(strict_types=1);

namespace Domain\Configuration\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationSchedulingSetting;

/**
 * Policy for Configuration Scheduling Settings.
 */
final class ConfigurationSchedulingSettingPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function view(AuthUser $user, ConfigurationSchedulingSetting $setting): bool
    {
        return $setting->tenant_id === $user->tenant_id;
    }

    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function update(AuthUser $user, ConfigurationSchedulingSetting $setting): bool
    {
        return $setting->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, ConfigurationSchedulingSetting $setting): bool
    {
        return $setting->tenant_id === $user->tenant_id;
    }
}
