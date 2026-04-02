<?php

declare(strict_types=1);

namespace Domain\Chat\Repositories;

use Domain\Chat\Models\ChatInstance;

/**
 * Eloquent implementation for resolving instances by webhook token.
 */
final class EloquentChatInstanceRepository implements ChatInstanceRepository
{
    public function resolveInstanceByToken(string $token): ?ChatInstance
    {
        return $this->buildTokenQuery($token, true)->first();
    }

    public function findByWebhookToken(string $token): ?ChatInstance
    {
        return $this->buildTokenQuery($token, false)->first();
    }

    /**
     * Builds the token lookup query (with or without active filter).
     *
     * @param  string  $token  Webhook token.
     * @param  bool  $onlyActive  Restrict to active instances.
     * @return \Illuminate\Database\Eloquent\Builder<ChatInstance>
     */
    private function buildTokenQuery(string $token, bool $onlyActive)
    {
        $query = ChatInstance::query();
        $connectionName = $query->getModel()->getConnectionName() ?: config('database.default');
        $driver = (string) config("database.connections.$connectionName.driver", 'pgsql');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $query->where(function ($q) use ($token, $driver): void {
            $q->where('webhook_token', $token);
            if ($driver === 'sqlite') {
                $q->orWhereRaw("json_extract(settings_json, '$.token') = ?", [$token])
                    ->orWhereRaw('settings_json LIKE ?', ['%"token":"'.$token.'"%']);
            } else {
                $q->orWhere('settings_json->token', $token);
            }
        });

        return $query;
    }
}
