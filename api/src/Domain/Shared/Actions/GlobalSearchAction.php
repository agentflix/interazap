<?php

declare(strict_types=1);

namespace Domain\Shared\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Shared\DTOs\GlobalSearchDTO;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Executa busca global com isolamento por tenant em múltiplos domínios.
 *
 * Consulta em paralelo contatos, empresas, negociações, tickets e usuários,
 * respeitando as permissões do usuário autenticado e aplicando cache por TTL.
 */
final class GlobalSearchAction
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array<string, array{total:int,items:list<array<string, mixed>>}>
     */
    public function handle(GlobalSearchDTO $dto): array
    {
        $cacheKey = $this->cacheKey($dto);

        // TODO: invalidate tenant tagged search cache on create/update/delete of searchable entities.
        return $this->cacheRepository($dto->tenantId)
            ->remember($cacheKey, self::CACHE_TTL_SECONDS, fn (): array => $this->search($dto));
    }

    /**
     * @return array<string, array{total:int,items:list<array<string, mixed>>}>
     */
    private function search(GlobalSearchDTO $dto): array
    {
        $results = [];

        if ($dto->shouldSearch('contacts') && Gate::check('crm.contacts.view')) {
            $results['contacts'] = $this->searchContacts($dto);
        }

        if ($dto->shouldSearch('companies') && Gate::check('crm.companies.view')) {
            $results['companies'] = $this->searchCompanies($dto);
        }

        if ($dto->shouldSearch('negotiations') && Gate::check('crm.negotiations.view')) {
            $results['negotiations'] = $this->searchNegotiations($dto);
        }

        if ($dto->shouldSearch('tickets') && Gate::check('chat.tickets.view')) {
            $results['tickets'] = $this->searchTickets($dto);
        }

        if ($dto->shouldSearch('users') && Gate::check('auth.users.view')) {
            $results['users'] = $this->searchUsers($dto);
        }

        return $results;
    }

    /**
     * @return array{total:int,items:list<array<string,mixed>>}
     */
    private function searchContacts(GlobalSearchDTO $dto): array
    {
        $query = CRMContact::query()
            ->where('tenant_id', $dto->tenantId)
            ->where(function (Builder $builder) use ($dto): void {
                $like = SearchSanitizer::likeContains($dto->query);
                $builder->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like)
                    ->orWhere('whatsapp', 'ilike', $like)
                    ->orWhere('document', 'ilike', $like);
            })
            ->with(['company:id,name'])
            ->orderBy('name');

        $total = (clone $query)->count();

        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $models = $query->limit($dto->perType)->get();

        $items = $models->map(static fn (CRMContact $model): array => [
            'id' => (string) $model->id,
            'type' => 'contact',
            'label' => (string) $model->name,
            'sublabel' => implode(' · ', array_values(array_filter([
                $model->email,
                $model->phone,
            ]))),
            'meta' => $model->company?->name,
            'url' => "/crm/contacts?contact_id={$model->id}",
            'icon' => 'tablerUser',
        ])->values()->all();

        return ['total' => $total, 'items' => $items];
    }

    /**
     * @return array{total:int,items:list<array<string,mixed>>}
     */
    private function searchCompanies(GlobalSearchDTO $dto): array
    {
        $query = CRMCompany::query()
            ->where('tenant_id', $dto->tenantId)
            ->where(function (Builder $builder) use ($dto): void {
                $like = SearchSanitizer::likeContains($dto->query);
                $builder->where('name', 'ilike', $like)
                    ->orWhere('document', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like);
            })
            ->orderBy('name');

        $total = (clone $query)->count();

        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $models = $query->limit($dto->perType)->get();

        $items = $models->map(static fn (CRMCompany $model): array => [
            'id' => (string) $model->id,
            'type' => 'company',
            'label' => (string) $model->name,
            'sublabel' => implode(' · ', array_values(array_filter([
                $model->document,
                $model->email,
            ]))),
            'meta' => null,
            'url' => "/crm/companies?company_id={$model->id}",
            'icon' => 'tablerBuildingStore',
        ])->values()->all();

        return ['total' => $total, 'items' => $items];
    }

    /**
     * @return array{total:int,items:list<array<string,mixed>>}
     */
    private function searchNegotiations(GlobalSearchDTO $dto): array
    {
        $query = CRMNegotiation::query()
            ->where('tenant_id', $dto->tenantId)
            ->where('title', 'ilike', SearchSanitizer::likeContains($dto->query))
            ->with(['contact:id,name', 'step:id,name'])
            ->orderBy('title');

        $total = (clone $query)->count();

        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $models = $query->limit($dto->perType)->get();

        $items = $models->map(static fn (CRMNegotiation $model): array => [
            'id' => (string) $model->id,
            'type' => 'negotiation',
            'label' => (string) $model->title,
            'sublabel' => implode(' · ', array_values(array_filter([
                $model->contact?->name,
                $model->step?->name,
            ]))),
            'meta' => $model->amount !== null ? (string) $model->amount : null,
            'url' => "/crm/negotiations/{$model->id}",
            'icon' => 'tablerReceipt2',
        ])->values()->all();

        return ['total' => $total, 'items' => $items];
    }

    /**
     * @return array{total:int,items:list<array<string,mixed>>}
     */
    private function searchTickets(GlobalSearchDTO $dto): array
    {
        $query = ChatTicket::query()
            ->where('tenant_id', $dto->tenantId)
            ->where(function (Builder $builder) use ($dto): void {
                $like = SearchSanitizer::likeContains($dto->query);
                $builder->whereHas('extended', function (Builder $extQuery) use ($like): void {
                    $extQuery->where('subject', 'ilike', $like);
                })
                    ->orWhere('protocol', 'ilike', $like)
                    ->orWhere('push_name', 'ilike', $like);
            })
            ->with(['contact:id,name', 'extended:id,ticket_id,subject'])
            ->orderByDesc('created_at');

        $total = (clone $query)->count();

        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $models = $query->limit($dto->perType)->get();

        $items = $models->map(static fn (ChatTicket $model): array => [
            'id' => (string) $model->id,
            'type' => 'ticket',
            'label' => (string) ($model->subject ?: "Ticket #{$model->protocol}"),
            'sublabel' => implode(' · ', array_values(array_filter([
                $model->protocol,
                $model->push_name,
            ]))),
            'meta' => $model->contact?->name,
            'url' => "/chat/{$model->id}",
            'icon' => 'tablerMessageCircle',
        ])->values()->all();

        return ['total' => $total, 'items' => $items];
    }

    /**
     * @return array{total:int,items:list<array<string,mixed>>}
     */
    private function searchUsers(GlobalSearchDTO $dto): array
    {
        $query = AuthUser::query()
            ->where('tenant_id', $dto->tenantId)
            ->where(function (Builder $builder) use ($dto): void {
                $like = SearchSanitizer::likeContains($dto->query);
                $builder->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like);
            })
            ->orderBy('name');

        $total = (clone $query)->count();

        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $models = $query->limit($dto->perType)->get();

        $items = $models->map(static fn (AuthUser $model): array => [
            'id' => (string) $model->id,
            'type' => 'user',
            'label' => (string) $model->name,
            'sublabel' => (string) $model->email,
            'meta' => null,
            'url' => '/settings/users',
            'icon' => 'tablerUserCircle',
        ])->values()->all();

        return ['total' => $total, 'items' => $items];
    }

    private function cacheKey(GlobalSearchDTO $dto): string
    {
        $hash = md5($dto->query.implode(',', $dto->types).$dto->perType);

        return "search:{$dto->tenantId}:{$hash}";
    }

    private function cacheRepository(string $tenantId): Repository
    {
        $repository = Cache::store();
        $store = $repository->getStore();

        if (method_exists($store, 'tags')) {
            return $repository->tags(['search', "tenant:{$tenantId}"]);
        }

        return $repository;
    }
}
