<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Casos de uso de leitura para prompts de tenants em quarentena.
 *
 * Contexto: operações administrativas que ignoram o TenantScope global
 * para acessar prompts pendentes de aprovação/rejeição em toda a plataforma.
 */
final class AiPromptQuarantineActions
{
    /**
     * Lista prompts em quarentena paginados com relações tenant e segment carregadas.
     *
     * Ignora o TenantScope global para permitir visão cross-tenant pelo admin.
     *
     * @param  int  $perPage  Itens por página.
     * @return LengthAwarePaginator<int, AiPromptTenant>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AiPromptTenant::query()
            ->withoutGlobalScope(TenantScope::class)
            ->quarantined()
            ->with(['tenant', 'segment'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * Busca um prompt pelo ID ignorando o TenantScope global.
     *
     * @param  string  $id  UUID do prompt.
     * @return AiPromptTenant Prompt encontrado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se não encontrado.
     */
    public function findById(string $id): AiPromptTenant
    {
        return AiPromptTenant::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
    }

    /**
     * Carrega as relações tenant e segment de um prompt.
     *
     * @param  AiPromptTenant  $prompt  Prompt a hidratar.
     * @return AiPromptTenant Prompt com relações carregadas.
     */
    public function loadPrompt(AiPromptTenant $prompt): AiPromptTenant
    {
        return $prompt->load(['tenant', 'segment']);
    }
}
