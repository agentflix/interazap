<?php

declare(strict_types=1);

namespace Domain\Chat\Repositories;

use Domain\Chat\Models\ChatInstance;

/**
 * Contract to resolve chat instances by webhook token.
 */
interface ChatInstanceRepository
{
    /**
     * Resolve an active instance from the webhook token.
     *
     * @param  string  $token  Webhook token.
     * @return ChatInstance|null Active instance or null when not found/inactive.
     */
    public function resolveInstanceByToken(string $token): ?ChatInstance;

    /**
     * Find instance by token without validating active status.
     *
     * @param  string  $token  Webhook token.
     * @return ChatInstance|null Found instance or null.
     */
    public function findByWebhookToken(string $token): ?ChatInstance;
}
