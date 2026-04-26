<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMProposalDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMProposal;
use Domain\CRM\Models\CRMProposalItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de uso para propostas comerciais.
 */
final class CRMProposalActions
{
    public function listByNegotiation(string $tenantId, string $negotiationId): LengthAwarePaginator
    {
        $this->guardNegotiation($tenantId, $negotiationId);

        return CRMProposal::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate();
    }

    public function find(string $tenantId, string $id): CRMProposal
    {
        return CRMProposal::query()
            ->where('tenant_id', $tenantId)
            ->with('items')
            ->findOrFail($id);
    }

    public function create(string $tenantId, CRMProposalDTO $dto): CRMProposal
    {
        $this->guardNegotiation($tenantId, $dto->crm_negotiation_id);

        return DB::transaction(function () use ($tenantId, $dto): CRMProposal {
            $proposal = CRMProposal::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                ...$dto->toArray(),
                'total' => 0,
            ]);

            $this->syncItems($proposal, $dto);

            return $proposal->load('items');
        });
    }

    public function update(string $tenantId, string $id, CRMProposalDTO $dto): CRMProposal
    {
        $proposal = $this->find($tenantId, $id);
        $this->guardNegotiation($tenantId, $proposal->crm_negotiation_id);

        return DB::transaction(function () use ($dto, $proposal): CRMProposal {
            $proposal->fill([
                'title' => $dto->title,
                'status' => $dto->status,
                'number' => $dto->number,
                'valid_until' => $dto->valid_until,
                'notes' => $dto->notes,
            ]);

            if ($dto->status === 'accepted') {
                $proposal->accepted_at = $proposal->accepted_at ?? now();
                $proposal->rejected_at = null;
            } elseif ($dto->status === 'rejected') {
                $proposal->rejected_at = $proposal->rejected_at ?? now();
                $proposal->accepted_at = null;
            } else {
                $proposal->accepted_at = null;
                $proposal->rejected_at = null;
            }
            $proposal->save();

            $this->syncItems($proposal, $dto);

            $this->handleNegotiationStatus($proposal, $dto->status);

            return $proposal->load('items');
        });
    }

    public function delete(string $tenantId, string $id): void
    {
        $proposal = $this->find($tenantId, $id);
        $proposal->delete();
    }

    public function send(string $tenantId, string $id): CRMProposal
    {
        $proposal = $this->find($tenantId, $id);
        $proposal->update([
            'status' => 'sent',
            'sent_at' => now(),
            'public_token' => $proposal->public_token ?? (string) Str::random(40),
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        return $proposal->fresh('items');
    }

    public function duplicate(string $tenantId, string $id): CRMProposal
    {
        $proposal = $this->find($tenantId, $id);

        return DB::transaction(function () use ($proposal): CRMProposal {
            $duplicate = CRMProposal::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $proposal->tenant_id,
                'crm_negotiation_id' => $proposal->crm_negotiation_id,
                'title' => $proposal->title.' (Cópia)',
                'status' => 'draft',
                'total' => 0,
                'public_token' => null,
                'sent_at' => null,
            ]);

            /** @var \Illuminate\Database\Eloquent\Collection<int, CRMProposalItem> $itemsCollection */
            $itemsCollection = $proposal->items()->get();

            $items = $itemsCollection->map(function (CRMProposalItem $item, int $index) use ($duplicate): array {
                return [
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $duplicate->tenant_id,
                    'crm_proposal_id' => $duplicate->id,
                    'crm_product_id' => $item->crm_product_id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount ?? 0,
                    'position' => $item->position ?? ($index + 1),
                    'total' => $item->total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            if ($items->isNotEmpty()) {
                CRMProposalItem::query()->insert($items->toArray());
                $duplicate->update(['total' => $items->sum('total')]);
            }

            return $duplicate->load('items');
        });
    }

    public function findByToken(string $token): CRMProposal
    {
        return CRMProposal::query()
            ->where('public_token', $token)
            ->with('items')
            ->firstOrFail();
    }

    public function markViewed(CRMProposal $proposal): CRMProposal
    {
        if ($proposal->viewed_at === null) {
            $proposal->update(['viewed_at' => now()]);
        }

        return $proposal->fresh('items');
    }

    private function guardNegotiation(string $tenantId, string $negotiationId): void
    {
        CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);
    }

    private function syncItems(CRMProposal $proposal, CRMProposalDTO $dto): void
    {
        $proposal->items()->delete();

        $total = 0;
        foreach ($dto->items as $index => $itemDto) {
            $data = $itemDto->toArray();
            $total += $data['total'];

            if (($data['position'] ?? 0) <= 0) {
                $data['position'] = $index + 1;
            }

            CRMProposalItem::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $proposal->tenant_id,
                'crm_proposal_id' => $proposal->id,
                ...$data,
            ]);
        }

        $proposal->update(['total' => $total]);
    }

    private function handleNegotiationStatus(CRMProposal $proposal, string $status): void
    {
        if (! in_array($status, ['accepted', 'rejected'], true)) {
            return;
        }

        $negotiation = $proposal->negotiation;
        if ($status === 'accepted') {
            $negotiation->status = 'won';
            $negotiation->closed_at = now();
            $negotiation->crm_reason_loss_id = null;
            $negotiation->save();
        }
    }
}
