<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\CRM\DTOs\CRMContactDTO;
use Domain\CRM\DTOs\CRMContactPhoneDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Domain\Shared\Support\ListFilterNormalizer;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de uso para contatos do CRM.
 */
final class CRMContactActions
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = CRMContact::query()
            ->select([
                'id',
                'tenant_id',
                'name',
                'email',
                'phone',
                'document',
                'whatsapp',
                'crm_company_id',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where('tenant_id', $tenantId)
            ->with(['company', 'phones', 'customFieldValues.field', 'tags']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('email', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('phone', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('whatsapp', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('document', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhereHas('company', function ($companyQuery) use ($search): void {
                            $companyQuery->where('name', 'ilike', SearchSanitizer::likeContains($search))
                                ->orWhere('document', 'ilike', SearchSanitizer::likeContains($search));
                        });
                });
            }
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $isActive = ListFilterNormalizer::normalizeIsActive($filters['is_active']);
            $query->where('is_active', (bool) $isActive);
        }

        if (! empty($filters['crm_company_id'])) {
            $query->where('crm_company_id', (string) $filters['crm_company_id']);
        }

        $allowedSortBy = ['name', 'email', 'created_at', 'updated_at', 'is_active'];
        $sortBy = ListFilterNormalizer::normalizeSortBy($filters['sort_by'] ?? null, $allowedSortBy, 'name');
        $sortDir = ListFilterNormalizer::normalizeSortDirection($filters['sort_dir'] ?? null);
        $perPage = ListFilterNormalizer::normalizePerPage($filters['per_page'] ?? null);

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    public function create(string $tenantId, CRMContactDTO $dto): CRMContact
    {
        return DB::transaction(function () use ($tenantId, $dto): CRMContact {
            $contact = CRMContact::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                ...$dto->toArray(),
            ]);

            if ($dto->phone_e164 !== null) {
                $this->createPhone($tenantId, new CRMContactPhoneDTO(
                    crm_contact_id: $contact->id,
                    phone_e164: $dto->phone_e164,
                    label: 'mobile',
                    is_primary: true
                ));
            }

            AutopilotTriggerFired::dispatch(
                $tenantId,
                AutopilotTriggerType::CONTACT_CREATED,
                [
                    'contact_id' => (string) $contact->id,
                    'name' => (string) $contact->name,
                    'phone' => (string) ($contact->phone ?? $dto->phone_e164 ?? ''),
                    'source_type' => 'contact',
                ],
                (string) $contact->id,
            );

            return $contact->load(['company', 'phones']);
        });
    }

    public function update(string $tenantId, string $id, CRMContactDTO $dto): CRMContact
    {
        $contact = $this->find($tenantId, $id);
        $contact->fill($dto->toArray());
        $contact->save();

        return $contact->load(['company', 'phones', 'customFieldValues.field', 'tags']);
    }

    /**
     * Atualizar parcialmente um contato CRM.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePartial(string $tenantId, string $id, array $data): CRMContact
    {
        $contact = $this->find($tenantId, $id);
        $contact->fill($data);
        $contact->save();

        return $contact->load(['company', 'phones', 'customFieldValues.field', 'tags']);
    }

    public function delete(string $tenantId, string $id): void
    {
        $contact = $this->find($tenantId, $id);
        $contact->delete();
    }

    public function restore(string $tenantId, string $id): CRMContact
    {
        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->withTrashed()
            ->findOrFail($id);

        $contact->restore();

        return $contact->load(['company', 'phones', 'customFieldValues.field', 'tags']);
    }

    public function createPhone(string $tenantId, CRMContactPhoneDTO $dto, bool $forceReassign = false): CRMContactPhone
    {
        return DB::transaction(function () use ($tenantId, $dto, $forceReassign): CRMContactPhone {
            $existingPhone = CRMContactPhone::query()
                ->where('tenant_id', $tenantId)
                ->where('phone_e164', $dto->phone_e164)
                ->whereNull('valid_to')
                ->lockForUpdate()
                ->first();

            if ($existingPhone) {
                if (! $forceReassign) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'phone_e164' => ['Telefone já cadastrado para o tenant.'],
                    ]);
                }

                $existingPhone->valid_to = now();
                $existingPhone->save();
            }

            return CRMContactPhone::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'valid_from' => now(),
                ...$dto->toArray(),
            ]);
        });
    }

    public function find(string $tenantId, string $id): CRMContact
    {
        return CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->with(['company', 'phones', 'customFieldValues.field', 'tags'])
            ->findOrFail($id);
    }

    /**
     * Buscar contato por telefone com dados de contexto para IA.
     *
     * Carrega relacionamentos essenciais: company, tags, negotiations (abertas).
     *
     * @param  string  $tenantId  ID do tenant.
     * @param  string  $phoneE164  Telefone no formato E.164.
     * @return CRMContact|null Contato encontrado ou null.
     */
    public function findByPhoneWithContext(string $tenantId, string $phoneE164): ?CRMContact
    {
        // 1) Buscar pelo campo principal phone/whatsapp do CRMContact
        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($phoneE164): void {
                $q->where('phone', $phoneE164)
                    ->orWhere('whatsapp', $phoneE164);
            })
            ->first();

        // 2) Se não encontrar, buscar pela tabela de telefones
        if (! $contact) {
            $contactPhone = CRMContactPhone::query()
                ->where('tenant_id', $tenantId)
                ->where('phone_e164', $phoneE164)
                ->whereNull('valid_to')
                ->first();

            if ($contactPhone) {
                /** @var CRMContact $contact */
                $contact = $contactPhone->contact;
            }
        }

        if (! $contact) {
            return null;
        }

        // Carregar relacionamentos essenciais com eager loading
        $contact->load([
            'company',
            'tags',
            'negotiations' => function ($query): void {
                $query->where('status', 'open')
                    ->with('step')
                    ->orderByDesc('created_at')
                    ->limit(3);
            },
        ]);

        return $contact;
    }
}
