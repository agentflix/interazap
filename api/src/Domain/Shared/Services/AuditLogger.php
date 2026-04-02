<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Serviço responsável por registrar ações de auditoria no sistema.
 *
 * Centraliza a criação de logs para rastreabilidade de mudanças em
 * modelos e ações administrativas realizadas por usuários.
 *
 * @category Services
 */
final class AuditLogger
{
    /**
     * Registrar uma nova entrada de auditoria.
     *
     * Cria um registro imutável contendo o autor, o evento e
     * o payload das alterações no modelo afetado.
     *
     * @param  AuthUser|null  $user  Usuário responsável pela ação (pode ser nulo em jobs).
     * @param  string  $tenantId  Identificador do tenant onde a ação ocorreu.
     * @param  string  $event  Nome do evento realizado (ex: 'created', 'updated').
     * @param  Model  $model  Instância do modelo que sofreu a alteração.
     * @param  array<string, mixed>  $changes  Lista opcional de mudanças (se vazio, usa getChanges).
     */
    public function log(?AuthUser $user, string $tenantId, string $event, Model $model, array $changes = []): void
    {
        $payload = $changes ?: $model->getChanges();

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'user_type' => $user?->getMorphClass(),
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'old_values' => null,
            'new_values' => $payload,
        ]);
    }
}
