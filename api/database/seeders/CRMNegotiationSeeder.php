<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Enums\CRMTaskStatus;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMNegotiationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const TASK_TITLES = [
        'Agendar reunião de diagnóstico',
        'Enviar proposta comercial',
        'Coletar documentos para aprovação',
        'Follow-up com decisor',
        'Validar integração técnica',
        'Negociar condições de pagamento',
    ];

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        $totalNegotiations = 0;

        foreach ($tenants as $tenant) {
            $companies = CRMCompany::query()->where('tenant_id', $tenant->id)->get();
            $contacts = CRMContact::query()->where('tenant_id', $tenant->id)->get();
            $funnels = CRMNegotiationFunnel::query()->where('tenant_id', $tenant->id)->get();
            $steps = CRMNegotiationFunnelStep::query()->where('tenant_id', $tenant->id)->get();

            if ($companies->isEmpty() || $contacts->isEmpty() || $funnels->isEmpty() || $steps->isEmpty()) {
                $this->command->warn(sprintf('Tenant %s sem dados base CRM suficientes para negociações.', $tenant->id));

                continue;
            }

            $products = CRMProduct::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();
            if ($products->isEmpty()) {
                $products = CRMProduct::factory()
                    ->count(12)
                    ->state(fn (): array => ['tenant_id' => $tenant->id])
                    ->create();
            }

            $reasons = CRMReasonLoss::query()->where('tenant_id', $tenant->id)->get();
            if ($reasons->isEmpty()) {
                $reasons = CRMReasonLoss::factory()
                    ->count(6)
                    ->state(fn (): array => ['tenant_id' => $tenant->id])
                    ->create();
            }

            $tags = CRMTag::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();
            $agents = AuthUser::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();

            for ($index = 1; $index <= 60; $index++) {
                $funnel = $funnels->random();
                $stepsForFunnel = $steps->where('crm_negotiation_funnel_id', $funnel->id)->values();

                if ($stepsForFunnel->isEmpty()) {
                    continue;
                }

                $status = fake()->randomElement([
                    CRMNegotiationStatus::OPEN->value,
                    CRMNegotiationStatus::OPEN->value,
                    CRMNegotiationStatus::WON->value,
                    CRMNegotiationStatus::LOST->value,
                ]);

                $step = $this->resolveStepForStatus($status, $stepsForFunnel->all());
                if (! $step instanceof CRMNegotiationFunnelStep) {
                    $step = $stepsForFunnel->random();
                }

                if ((string) $step->crm_negotiation_funnel_id !== (string) $funnel->id) {
                    continue;
                }

                $company = $companies->random();
                $contactsForCompany = $contacts->where('crm_company_id', $company->id);
                $contact = $contactsForCompany->isNotEmpty()
                    ? $contactsForCompany->random()
                    : $contacts->random();

                if ((string) $contact->tenant_id !== (string) $tenant->id) {
                    continue;
                }

                $selectedProducts = $products->random(random_int(1, min(3, $products->count())));
                $lineItems = collect();

                foreach ($selectedProducts as $product) {
                    $quantity = random_int(1, 5);
                    $unitPrice = (float) $product->price;
                    $lineItems->push([
                        'product' => $product,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $quantity * $unitPrice,
                    ]);
                }

                $amount = (float) $lineItems->sum('total');
                $closedAt = null;
                $expectedClose = now()->addDays(random_int(10, 60));
                $reasonLossId = null;

                if ($status === CRMNegotiationStatus::WON->value) {
                    $closedAt = now()->subDays(random_int(1, 45));
                    $expectedClose = null;
                }

                if ($status === CRMNegotiationStatus::LOST->value) {
                    $closedAt = now()->subDays(random_int(1, 60));
                    $expectedClose = null;
                    $reasonLossId = $reasons->random()->id;
                }

                $mainProduct = $selectedProducts->first();
                $title = sprintf('%s • %s', $mainProduct->name ?? 'Negociação', $company->name);

                $negotiation = CRMNegotiation::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'crm_contact_id' => $contact->id,
                        'title' => $title,
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'auth_user_id' => $agents->isNotEmpty() ? $agents->random()->id : null,
                        'crm_company_id' => $company->id,
                        'crm_negotiation_funnel_id' => $funnel->id,
                        'crm_negotiation_funnel_step_id' => $step->id,
                        'crm_reason_loss_id' => $reasonLossId,
                        'amount' => $amount,
                        'status' => $status,
                        'position' => $index,
                        'expected_close' => $expectedClose,
                        'closed_at' => $closedAt,
                        'lead_score' => random_int(25, 98),
                    ]
                );

                foreach ($lineItems as $line) {
                    CRMNegotiationProduct::query()->firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'crm_negotiation_id' => $negotiation->id,
                            'crm_product_id' => $line['product']->id,
                        ],
                        [
                            'id' => (string) Str::orderedUuid(),
                            'name' => $line['product']->name,
                            'quantity' => $line['quantity'],
                            'unit_price' => $line['unit_price'],
                            'total' => $line['total'],
                        ]
                    );
                }

                $taskCount = random_int(1, 3);
                for ($taskIndex = 0; $taskIndex < $taskCount; $taskIndex++) {
                    $taskTitle = self::TASK_TITLES[($index + $taskIndex) % count(self::TASK_TITLES)];
                    CRMNegotiationTask::query()->firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'crm_negotiation_id' => $negotiation->id,
                            'title' => $taskTitle,
                        ],
                        [
                            'id' => (string) Str::orderedUuid(),
                            'auth_user_id' => $agents->isNotEmpty() ? $agents->random()->id : null,
                            'description' => 'Tarefa gerada automaticamente para simulação de pipeline comercial.',
                            'status' => fake()->randomElement([
                                CRMTaskStatus::PENDING->value,
                                CRMTaskStatus::IN_PROGRESS->value,
                                CRMTaskStatus::DONE->value,
                            ]),
                            'due_date' => now()->addDays(random_int(2, 21)),
                        ]
                    );
                }

                if ($tags->isNotEmpty()) {
                    $tagIds = $tags->random(random_int(1, min(2, $tags->count())))->pluck('id')->all();

                    foreach ($tagIds as $tagId) {
                        $negotiation->tags()->syncWithoutDetaching([
                            $tagId => [
                                'id' => (string) Str::orderedUuid(),
                                'tenant_id' => $tenant->id,
                            ],
                        ]);
                    }
                }

                $totalNegotiations++;
            }
        }

        $this->command->info(sprintf('Negotiations created: %d', $totalNegotiations));
    }

    /**
     * @param  array<int, CRMNegotiationFunnelStep>  $steps
     */
    private function resolveStepForStatus(string $status, array $steps): ?CRMNegotiationFunnelStep
    {
        if ($status === CRMNegotiationStatus::WON->value) {
            return collect($steps)->first(fn (CRMNegotiationFunnelStep $step): bool => str_contains(mb_strtolower($step->name), 'ganho'));
        }

        if ($status === CRMNegotiationStatus::LOST->value) {
            return collect($steps)->first(fn (CRMNegotiationFunnelStep $step): bool => str_contains(mb_strtolower($step->name), 'perd'));
        }

        $openSteps = collect($steps)->reject(
            fn (CRMNegotiationFunnelStep $step): bool => str_contains(mb_strtolower($step->name), 'ganho') || str_contains(mb_strtolower($step->name), 'perd')
        );

        return $openSteps->isNotEmpty() ? $openSteps->random() : collect($steps)->random();
    }
}
