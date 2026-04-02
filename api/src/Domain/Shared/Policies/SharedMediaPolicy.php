<?php

declare(strict_types=1);

namespace Domain\Shared\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Domain\Shared\Models\SharedMedia;

/**
 * Policy para uploads de mídia compartilhada.
 */
final class SharedMediaPolicy
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

    public function create(AuthUser $user, ?SharedMedia $media = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantId = (string) $user->tenant_id;
        if ($tenantId === '') {
            return false;
        }

        if (! $this->enforcementService->canUploadFile($tenantId)) {
            throw PlanLimitExceededException::forResource('storage');
        }

        return true;
    }

    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    public function view(AuthUser $user, SharedMedia $media): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $media->tenant_id === $user->tenant_id;
    }

    public function update(AuthUser $user, SharedMedia $media): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $media->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, SharedMedia $media): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $media->tenant_id === $user->tenant_id;
    }
}
