<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Auth\Models\AuthUser;

final class AiAgentPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('ai.autopilots.manage');
    }

    public function view(AuthUser $user): bool
    {
        return $user->can('ai.autopilots.manage');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('ai.autopilots.manage');
    }

    public function update(AuthUser $user): bool
    {
        return $user->can('ai.autopilots.manage');
    }

    public function delete(AuthUser $user): bool
    {
        return $user->can('ai.autopilots.manage');
    }
}
