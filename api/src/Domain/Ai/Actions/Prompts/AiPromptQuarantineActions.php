<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Actions para prompts em Quarentena.
 */
final class AiPromptQuarantineActions
{
    /**
     * Listar prompts em Quarentena com paginação.
     *
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
     * Buscar prompt (ignora o tenant scope).
     */
    public function findById(string $id): AiPromptTenant
    {
        return AiPromptTenant::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
    }

    /**
     * Carregar relações do prompt.
     */
    public function loadPrompt(AiPromptTenant $prompt): AiPromptTenant
    {
        return $prompt->load(['tenant', 'segment']);
    }
}
