<?php

declare(strict_types=1);

namespace Domain\CRM\Services;

use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies reusable CRM negotiation listing filters.
 */
final class CRMNegotiationFilterService
{
    /**
     * @param  Builder<CRMNegotiation>  $query
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters, bool $defaultOpenStatus): void
    {
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $query->where('status', $status);
        } elseif ($defaultOpenStatus) {
            $query->where('status', CRMNegotiationStatus::OPEN->value);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'ilike', SearchSanitizer::likeContains($search))
                    ->orWhereHas('company', fn (Builder $companyQuery) => $companyQuery->where('name', 'ilike', SearchSanitizer::likeContains($search)))
                    ->orWhereHas('contact', fn (Builder $contactQuery) => $contactQuery->where('name', 'ilike', SearchSanitizer::likeContains($search)));
            });
        }

        if (! empty($filters['funnel_id'])) {
            $query->where('crm_negotiation_funnel_id', (string) $filters['funnel_id']);
        }

        if (! empty($filters['step_id'])) {
            $query->where('crm_negotiation_funnel_step_id', (string) $filters['step_id']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('crm_contact_id', (string) $filters['contact_id']);
        }

        if (! empty($filters['crm_company_id'])) {
            $query->where('crm_company_id', (string) $filters['crm_company_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->whereHas('tasks', fn (Builder $taskQuery) => $taskQuery->where('auth_user_id', (string) $filters['user_id']));
        }

        if (! empty($filters['reason_loss_id'])) {
            $query->where('crm_reason_loss_id', (string) $filters['reason_loss_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['expected_close_from'])) {
            $query->whereDate('expected_close', '>=', (string) $filters['expected_close_from']);
        }

        if (! empty($filters['expected_close_to'])) {
            $query->whereDate('expected_close', '<=', (string) $filters['expected_close_to']);
        }

        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $query->where('amount', '>=', (float) $filters['amount_min']);
        }

        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $query->where('amount', '<=', (float) $filters['amount_max']);
        }

        if (isset($filters['lead_score_min']) && $filters['lead_score_min'] !== '') {
            $query->where('lead_score', '>=', (int) $filters['lead_score_min']);
        }

        if (isset($filters['lead_score_max']) && $filters['lead_score_max'] !== '') {
            $query->where('lead_score', '<=', (int) $filters['lead_score_max']);
        }

        $tagIds = $this->normalizeTagIds($filters['tag_ids'] ?? null);
        if ($tagIds !== []) {
            $query->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('crm_tags.id', $tagIds));
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('products', fn (Builder $productQuery) => $productQuery->where('crm_product_id', (string) $filters['product_id']));
        }

        if (array_key_exists('has_pending_tasks', $filters)) {
            $hasPendingTasks = filter_var($filters['has_pending_tasks'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($hasPendingTasks === true) {
                $query->whereHas('tasks', fn (Builder $taskQuery) => $taskQuery->whereIn('status', ['pending', 'in_progress']));
            }
            if ($hasPendingTasks === false) {
                $query->whereDoesntHave('tasks', fn (Builder $taskQuery) => $taskQuery->whereIn('status', ['pending', 'in_progress']));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeTagIds(mixed $tagIds): array
    {
        if ($tagIds === null || $tagIds === '') {
            return [];
        }

        if (is_string($tagIds)) {
            $tagIds = array_filter(explode(',', $tagIds));
        }

        if (! is_array($tagIds)) {
            return [];
        }

        $normalized = [];
        foreach ($tagIds as $tagId) {
            $value = trim((string) $tagId);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Aplica filtros a uma query aggregate (GROUP BY).
     *
     * Usa apenas condições WHERE simples (sem eager loads) para não invalidar
     * a clause GROUP BY. Equivale ao apply() mas sem os filtros que usam subqueries
     * que dependam de relações carregadas.
     *
     * @param  Builder<CRMNegotiation>  $query
     * @param  array<string, mixed>  $filters
     */
    public function applyForAggregate(Builder $query, array $filters, bool $defaultOpenStatus = true): void
    {
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $query->where('status', $status);
        } elseif ($defaultOpenStatus) {
            $query->where('status', \Domain\CRM\Enums\CRMNegotiationStatus::OPEN->value);
        }

        if (! empty($filters['step_id'])) {
            $query->where('crm_negotiation_funnel_step_id', (string) $filters['step_id']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('crm_contact_id', (string) $filters['contact_id']);
        }

        if (! empty($filters['crm_company_id'])) {
            $query->where('crm_company_id', (string) $filters['crm_company_id']);
        }

        if (! empty($filters['reason_loss_id'])) {
            $query->where('crm_reason_loss_id', (string) $filters['reason_loss_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['expected_close_from'])) {
            $query->whereDate('expected_close', '>=', (string) $filters['expected_close_from']);
        }

        if (! empty($filters['expected_close_to'])) {
            $query->whereDate('expected_close', '<=', (string) $filters['expected_close_to']);
        }

        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $query->where('amount', '>=', (float) $filters['amount_min']);
        }

        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $query->where('amount', '<=', (float) $filters['amount_max']);
        }

        if (isset($filters['lead_score_min']) && $filters['lead_score_min'] !== '') {
            $query->where('lead_score', '>=', (int) $filters['lead_score_min']);
        }

        if (isset($filters['lead_score_max']) && $filters['lead_score_max'] !== '') {
            $query->where('lead_score', '<=', (int) $filters['lead_score_max']);
        }
    }
}
