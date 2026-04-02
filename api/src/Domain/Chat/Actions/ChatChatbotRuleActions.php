<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatChatbotRuleDTO;
use Domain\Chat\Models\ChatChatbotRule;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Casos de Uso para Regras de Chatbot.
 *
 * Centraliza a lógica de gestão de regras de automação (chatbot) para o chat,
 * incluindo triagem, auto-resposta e atribuição automática.
 *
 * @category Actions
 */
final class ChatChatbotRuleActions
{
    /**
     * Listar todas as regras do tenant com paginação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return LengthAwarePaginator Paginador com registros de regras.
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        $query = ChatChatbotRule::query()
            ->where('tenant_id', $tenantId);

        if (request()->routeIs('api.chat.chatbot-rules.*')) {
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
     * @param  ChatChatbotRuleDTO  $dto  Dados estruturados da regra.
     * @return ChatChatbotRule Modelo criado.
     */
    public function create(string $tenantId, ChatChatbotRuleDTO $dto): ChatChatbotRule
    {
        return ChatChatbotRule::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /**
     * Atualizar uma regra existente.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  UUID da regra.
     * @param  ChatChatbotRuleDTO  $dto  Novos dados da regra.
     * @return ChatChatbotRule Modelo atualizado.
     */
    public function update(string $tenantId, string $id, ChatChatbotRuleDTO $dto): ChatChatbotRule
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
     * @return ChatChatbotRule Modelo encontrado ou erro 404.
     */
    public function find(string $tenantId, string $id): ChatChatbotRule
    {
        return ChatChatbotRule::query()
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
        // Normaliza a keyword (lowercase, sem acentos, espaços colapsados)
        $normalized = $this->normalizeKeyword($keyword);
        if ($normalized === '') {
            return false;
        }

        $query = ChatChatbotRule::query()
            ->where('tenant_id', $tenantId);

        // Ignorar regra específica (para edição)
        if ($ignoreRuleId !== null) {
            $query->where('id', '!=', $ignoreRuleId);
        }

        // Verificar se existe conflito no trigger_text (comparação case-insensitive)
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
        // Converter para minúsculas
        $normalized = mb_strtolower(trim($keyword));

        // Remover acentos
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;

        // Colapsar espaços múltiplos
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }
}
