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

    public function find(string $id): PlatformPlan
    {
        return PlatformPlan::query()->findOrFail($id);
    }

    public function toggle(string $id): PlatformPlan
    {
        $plan = $this->find($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        return $plan->refresh();
    }

    public function validateSlug(string $slug, ?string $planId = null): bool
    {
        $query = PlatformPlan::query()->where('slug', $slug);
        if ($planId) {
            $query->where('id', '!=', $planId);
        }

        return ! $query->exists();
    }
}
