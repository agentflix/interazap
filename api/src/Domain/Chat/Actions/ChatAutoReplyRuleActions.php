<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatAutoReplyRuleDTO;
use Domain\Chat\Models\ChatAutoReplyRule;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Casos de Uso para Regras de Auto Reply.
 *
 * Centraliza a lógica de gestão de regras de automação (auto reply) para o chat,
 * incluindo triagem, auto-resposta e atribuição automática.
 *
 * @category Actions
 */
final class ChatAutoReplyRuleActions
{
    /**
     * Listar todas as regras do tenant com paginação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return LengthAwarePaginator Paginador com registros de regras.
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        $query = ChatAutoReplyRule::query()
            ->where('tenant_id', $tenantId);

        if (request()->routeIs('api.chat.auto-reply-rules.*')) {
            $query->where('is_welcome', false);
        }

        return $query
            ->latest()
            ->paginate();
    }

    /**
     * Criar uma nova regra de automação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatAutoReplyRuleDTO  $dto  Dados estruturados da regra.
     * @return ChatAutoReplyRule Modelo criado.
     */
    public function create(string $tenantId, ChatAutoReplyRuleDTO $dto): ChatAutoReplyRule
    {
        return ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /**
     * Atualizar uma regra existente.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  UUID da regra.
     * @param  ChatAutoReplyRuleDTO  $dto  Novos dados da regra.
     * @return ChatAutoReplyRule Modelo atualizado.
     */
    public function update(string $tenantId, string $id, ChatAutoReplyRuleDTO $dto): ChatAutoReplyRule
    {
        $rule = $this->find($tenantId, $id);
        $rule->fill($dto->toArray());
        $rule->save();

        return $rule;
    }

    /**
     * Remover uma regra de automação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  UUID da regra.
     */
    public function delete(string $tenantId, string $id): void
    {
        $rule = $this->find($tenantId, $id);
        $rule->delete();
    }

    /**
     * Localizar uma regra específica pelo ID e Tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  UUID da regra.
     * @return ChatAutoReplyRule Modelo encontrado ou erro 404.
     */
    public function find(string $tenantId, string $id): ChatAutoReplyRule
    {
        return ChatAutoReplyRule::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * Verifica se uma palavra-chave está disponível para uso.
     *
     * Verifica se já existe outra regra com o mesmo trigger_text no tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $keyword  Palavra-chave a verificar.
     * @param  string  $matchType  Tipo de correspondência (não usado atualmente).
     * @param  string|null  $instanceId  ID da instância (não usado atualmente).
     * @param  string|null  $departmentId  ID do departamento (não usado atualmente).
     * @param  string|null  $ignoreRuleId  ID da regra a ignorar na verificação (para edição).
     * @return bool True se a palavra-chave está disponível.
     */
    public function isKeywordAvailable(
        string $tenantId,
        string $keyword,
        string $matchType = 'contains',
        ?string $instanceId = null,
        ?string $departmentId = null,
        ?string $ignoreRuleId = null,
    ): bool {
        $normalized = $this->normalizeKeyword($keyword);
        if ($normalized === '') {
            return false;
        }

        $query = ChatAutoReplyRule::query()
            ->where('tenant_id', $tenantId);

        if ($ignoreRuleId !== null) {
            $query->where('id', '!=', $ignoreRuleId);
        }

        $exists = $query
            ->whereRaw('LOWER(trigger_text) = ?', [$normalized])
            ->exists();

        return ! $exists;
    }

    /**
     * Normaliza uma palavra-chave para comparação.
     *
     * @param  string  $keyword  Palavra-chave original.
     * @return string Palavra-chave normalizada.
     */
    private function normalizeKeyword(string $keyword): string
    {
        $normalized = mb_strtolower(trim($keyword));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }
}
