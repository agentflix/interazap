<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Platform\Models\PlatformPlan;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Consultas e ações simples para planos da plataforma.
 */
final class PlatformPlanQueryActions
{
    /**
     * Lista planos com busca por nome/slug e paginação.
     *
     * @param  string  $search  Texto de busca opcional.
     * @param  int  $perPage  Itens por página.
     * @return LengthAwarePaginator Lista paginada de planos.
     */
    public function list(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        $query = PlatformPlan::query();

        if ($search !== '') {
            $operator = DB::getDriverName() === 'sqlite' ? 'like' : 'ilike';
            $query->where(function ($sub) use ($search, $operator): void {
                $sub->where('name', $operator, SearchSanitizer::likeContains($search))
                    ->orWhere('slug', $operator, SearchSanitizer::likeContains($search));
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Busca um plano pelo ID ou lança exceção se não encontrado.
     *
     * @param  string  $id  UUID do plano.
     * @return PlatformPlan Plano encontrado.
     */
    public function find(string $id): PlatformPlan
    {
        return PlatformPlan::query()->findOrFail($id);
    }

    /**
     * Alterna o status ativo/inativo de um plano.
     *
     * @param  string  $id  UUID do plano.
     * @return PlatformPlan Plano com status atualizado.
     */
    public function toggle(string $id): PlatformPlan
    {
        $plan = $this->find($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        return $plan->refresh();
    }

    /**
     * Verifica se o slug está disponível, excluindo opcionalmente o plano atual na edição.
     *
     * @param  string  $slug  Slug a verificar.
     * @param  string|null  $planId  UUID do plano a ignorar na verificação (para update).
     * @return bool Verdadeiro se o slug está disponível.
     */
    public function validateSlug(string $slug, ?string $planId = null): bool
    {
        $query = PlatformPlan::query()->where('slug', $slug);
        if ($planId) {
            $query->where('id', '!=', $planId);
        }

        return ! $query->exists();
    }
}
