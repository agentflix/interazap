<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Auth\Models\AuthUser;

final class AiAgentPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.view');
    }

    public function view(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.view');
    }

    public function create(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.manage');
    }

    public function update(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.manage');
    }

    public function delete(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.manage');
    }

    private function canWithManageFallback(AuthUser $user, string $permission): bool
    {
        return $user->can($permission) || $user->can('ai.autopilots.manage');
    }
}
