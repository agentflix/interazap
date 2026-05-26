<?php

declare(strict_types=1);

namespace Domain\Chat\Repositories;

use Domain\Chat\Models\ChatInstance;

/**
 * Implementação Eloquent do repositório de instâncias de chat.
 *
 * Suporta lookup por `webhook_token` direto ou pelo campo `settings_json->token`,
 * com compatibilidade para PostgreSQL (jsonb) e SQLite (json_extract).
 */
final class EloquentChatInstanceRepository implements ChatInstanceRepository
{
    /** Retorna a primeira instância ativa que corresponda ao token informado. */
    public function resolveInstanceByToken(string $token): ?ChatInstance
    {
        return $this->buildTokenQuery($token, true)->first();
    }

    /** Busca instância pelo token sem filtro de status de ativação. */
    public function findByWebhookToken(string $token): ?ChatInstance
    {
        return $this->buildTokenQuery($token, false)->first();
    }

    /**
     * Constrói a query de busca por token com suporte multi-driver.
     *
     * @param  string  $token  Token de webhook a pesquisar.
     * @param  bool  $onlyActive  Quando true, filtra apenas instâncias ativas.
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
