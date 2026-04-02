<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Enums\CRMProposalStatus;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMProposal;
use Domain\CRM\Models\CRMProposalItem;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMProposalSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        foreach ($tenants as $tenant) {
            $negotiations = CRMNegotiation::query()
                ->with(['products'])
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', [
                    CRMNegotiationStatus::OPEN->value,
                    CRMNegotiationStatus::WON->value,
                ])
                ->limit(25)
                ->get();

            if ($negotiations->isEmpty()) {
                continue;
            }

            $baseNumber = (int) CRMProposal::query()->where('tenant_id', $tenant->id)->max('number');
            $number = $baseNumber > 0 ? $baseNumber : 1000;

            foreach ($negotiations as $negotiation) {
                $number++;

                $negotiationStatus = $negotiation->status instanceof CRMNegotiationStatus
                    ? $negotiation->status->value
                    : (string) $negotiation->status;

                $status = $this->resolveProposalStatus($negotiationStatus);
                $validUntil = now()->addDays(random_int(7, 30))->toDateString();
                $publicToken = in_array($status, [CRMProposalStatus::SENT->value, CRMProposalStatus::ACCEPTED->value], true)
                    ? (string) Str::uuid()
                    : null;

                $proposal = CRMProposal::query()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'crm_negotiation_id' => $negotiation->id,
                    'number' => $number,
                ]);

                if (! $proposal->exists) {
                    $proposal->id = (string) Str::orderedUuid();
                }

                $proposal->fill([
                    'title' => sprintf('Proposta Comercial - %s', $negotiation->title),
                    'total' => 0,
                    'status' => $status,
                    'valid_until' => $validUntil,
                    'public_token' => $publicToken,
                    'notes' => 'Condição comercial simulada para ambiente de demonstração.',
                    'sent_at' => in_array($status, [CRMProposalStatus::SENT->value, CRMProposalStatus::ACCEPTED->value, CRMProposalStatus::REJECTED->value], true) ? now()->subDays(random_int(1, 10)) : null,
                    'viewed_at' => in_array($status, [CRMProposalStatus::ACCEPTED->value, CRMProposalStatus::REJECTED->value], true) ? now()->subDays(random_int(1, 5)) : null,
                    'accepted_at' => $status === CRMProposalStatus::ACCEPTED->value ? now()->subDays(random_int(0, 3)) : null,
                    'rejected_at' => $status === CRMProposalStatus::REJECTED->value ? now()->subDays(random_int(0, 3)) : null,
                ]);
                $proposal->save();

                CRMProposalItem::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('crm_proposal_id', $proposal->id)
                    ->delete();

                $items = $negotiation->products;
                $line = 1;
                $total = 0.0;

                foreach ($items as $item) {
                    $lineTotal = ((float) $item->quantity * (float) $item->unit_price) - (float) 0;

                    CRMProposalItem::query()->create([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenant->id,
                        'crm_proposal_id' => $proposal->id,
                        'crm_product_id' => $item->crm_product_id,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => 0,
                        'total' => $lineTotal,
                        'position' => $line,
                    ]);

                    $line++;
                    $total += $lineTotal;
                }

                if ($total <= 0) {
                    continue;
                }

                $proposal->total = $total;
                $proposal->save();
            }
        }

        $this->command->info('CRM proposals e itens simulados criados com sucesso.');
    }

    private function resolveProposalStatus(string $negotiationStatus): string
    {
        if ($negotiationStatus === CRMNegotiationStatus::WON->value) {
            return CRMProposalStatus::ACCEPTED->value;
        }

        return fake()->randomElement([
            CRMProposalStatus::DRAFT->value,
            CRMProposalStatus::SENT->value,
            CRMProposalStatus::REJECTED->value,
        ]);
    }
}
