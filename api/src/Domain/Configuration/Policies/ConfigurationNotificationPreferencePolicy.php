<?php

declare(strict_types=1);

namespace Domain\Configuration\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationNotificationPreference;

/**
 * Policy for Configuration Notification Preferences.
 */
final class ConfigurationNotificationPreferencePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function view(AuthUser $user, ConfigurationNotificationPreference $preference): bool
    {
        return $preference->tenant_id === $user->tenant_id
            && $preference->user_id === $user->id;
    }

    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function update(AuthUser $user, ?ConfigurationNotificationPreference $preference = null): bool
    {
        if ($preference === null) {
            return (bool) $user->tenant_id;
        }

        return $preference->tenant_id === $user->tenant_id
            && $preference->user_id === $user->id;
    }

    public function delete(AuthUser $user, ConfigurationNotificationPreference $preference): bool
    {
        return $preference->tenant_id === $user->tenant_id
            && $preference->user_id === $user->id;
    }
}
