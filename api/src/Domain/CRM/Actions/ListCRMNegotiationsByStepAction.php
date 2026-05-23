<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Services\CRMNegotiationFilterService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Busca negociações de uma etapa específica com cursor-based pagination.
 *
 * O cursor composto (position, id) garante performance constante O(log n) via índice,
 * independente do volume de dados, e mantém estabilidade durante operações de drag-and-drop.
 */
final class ListCRMNegotiationsByStepAction
{
    public function __construct(
        private readonly CRMNegotiationFilterService $filterService,
    ) {}

    /**
     * Buscar negociações paginadas por cursor para uma etapa do kanban.
     *
     * @param  string  $tenantId  ID do tenant
     * @param  string  $funnelId  ID do funil
     * @param  string  $stepId  ID da etapa
     * @param  array<string, mixed>|null  $cursor  Cursor: ['position' => int, 'id' => string]
     * @param  int  $perPage  Itens por página (máx 50)
     * @param  array<string, mixed>  $filters  Filtros adicionais
     * @return array{negotiations: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function execute(
        string $tenantId,
        string $funnelId,
        string $stepId,
        ?array $cursor,
        int $perPage,
        array $filters = [],
    ): array {
        $perPage = min($perPage, 50);
        $limit = $perPage + 1;

        $query = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_id', $funnelId)
            ->where('crm_negotiation_funnel_step_id', $stepId)
            ->with(['company', 'contact', 'tags', 'customFieldValues.field', 'user'])
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit);

        if ($cursor !== null) {
            $cursorPosition = (int) $cursor['position'];
            $cursorId = (string) $cursor['id'];

            $query->where(function (Builder $builder) use ($cursorPosition, $cursorId): void {
                $builder->where('position', '>', $cursorPosition)
                    ->orWhere(function (Builder $inner) use ($cursorPosition, $cursorId): void {
                        $inner->where('position', $cursorPosition)
                            ->where('id', '>', $cursorId);
                    });
            });
        }

        $this->filterService->apply($query, $filters, false);

        // Negociações com amount > 0 devem ter ao menos um produto vinculado
        // (crm_negotiation_products com crm_product_id não nulo).
        $query->where(function (Builder $builder): void {
            $builder->where('amount', '<=', 0)
                ->orWhereHas('products', fn (Builder $productQuery) => $productQuery->whereNotNull('crm_product_id'));
        });

        $items = $query->get();
        $hasMore = $items->count() > $perPage;

        if ($hasMore) {
            $items = $items->take($perPage);
        }

        $step = $stepId ? CRMNegotiationFunnelStep::find($stepId) : null;

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $last = $items->last();
            $nextCursor = base64_encode((string) json_encode([
                'position' => (int) ($last->position ?? 0),
                'id' => (string) $last->id,
            ]));
        }

        return [
            'negotiations' => $items->map(fn (CRMNegotiation $negotiation): array => $this->serializeNegotiation($negotiation, $step))->values()->all(),
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * Decodificar cursor de paginação.
     *
     * @return array{position: int, id: string}|null
     */
    public static function decodeCursor(string $cursor): ?array
    {
        $decoded = base64_decode($cursor, strict: true);
        if ($decoded === false) {
            return null;
        }

        /** @var array{position?: mixed, id?: mixed}|null $data */
        $data = json_decode($decoded, associative: true);
        if (! is_array($data) || ! isset($data['position'], $data['id'])) {
            return null;
        }

        return [
            'position' => (int) $data['position'],
            'id' => (string) $data['id'],
        ];
    }

    /**
     * Serializar negociação para resposta da API.
     *
     * @return array<string, mixed>
     */
    private function serializeNegotiation(CRMNegotiation $negotiation, ?CRMNegotiationFunnelStep $step): array
    {
        return [
            'id' => $negotiation->id,
            'title' => $negotiation->title,
            'value' => (float) $negotiation->amount,
            'status' => $negotiation->status instanceof \BackedEnum ? $negotiation->status->value : $negotiation->status,
            'position' => (int) ($negotiation->position ?? 0),
            'contact_id' => $negotiation->crm_contact_id,
            'crm_company_id' => $negotiation->crm_company_id,
            'funnel_id' => $negotiation->crm_negotiation_funnel_id,
            'step_id' => $negotiation->crm_negotiation_funnel_step_id,
            'created_at' => $this->toIsoString($negotiation->created_at),
            'updated_at' => $this->toIsoString($negotiation->updated_at),
            'contact' => $negotiation->contact ? [
                'id' => $negotiation->contact->id,
                'name' => $negotiation->contact->name,
            ] : null,
            'crm_company' => $negotiation->company instanceof CRMCompany ? [
                'id' => $negotiation->company->id,
                'name' => $negotiation->company->name,
            ] : null,
            'step' => $step ? [
                'id' => $step->id,
                'funnel_id' => $step->crm_negotiation_funnel_id,
                'name' => $step->name,
                'color' => $step->color,
                'order' => (int) $step->order,
                'is_active' => (bool) $step->is_active,
            ] : null,
            'user' => $negotiation->user ? [
                'id' => $negotiation->user->id,
                'name' => $negotiation->user->name,
            ] : null,
            'tags' => $negotiation->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color ?? null,
            ])->values()->all(),
        ];
    }

    private function toIsoString(mixed $value): ?string
    {
        if (! $value instanceof \DateTimeInterface) {
            return null;
        }

        return $value->format(DATE_ATOM);
    }
}
