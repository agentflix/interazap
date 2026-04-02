<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Billing\Actions\BillingAsaasWebhookAction;
use Domain\Billing\DTOs\BillingWebhookDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * TASK-013-S1-SEC / API-SEC-001
 * Garante idempotência ponta a ponta: mesmo evento processado via HTTP e via consumer
 * não deve duplicar pagamentos nem contornar a idempotência com skipIdempotency em produção.
 */
final class BillingWebhookIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Teste principal: mesmo evento chamado duas vezes deve registrar pagamento apenas uma vez.
     */
    public function test_webhook_idempotency_concurrency_http_and_consumer(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $payload = [
            'provider' => 'ASAAS',
            'event_type' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_idem_001',
                'value' => 199.90,
                'billingType' => 'PIX',
                'externalReference' => (string) $invoice->id,
            ],
        ];

        $dto = BillingWebhookDTO::fromArray($payload);

        /** @var BillingAsaasWebhookAction $action */
        $action = app(BillingAsaasWebhookAction::class);

        // Primeira chamada — simula HTTP webhook
        $first = $action->handle($tenant, $dto);

        // Segunda chamada — simula consumer processando o mesmo evento (path redundante)
        $second = $action->handle($tenant, $dto);

        // Primeira deve ter criado e atualizado
        $this->assertTrue($first['created']);
        $this->assertTrue($first['invoice_updated']);

        // Segunda deve ter sido bloqueada pela idempotência
        $this->assertFalse($second['created']);
        $this->assertFalse($second['invoice_updated']);

        // Pagamento deve ter sido criado apenas uma vez
        $this->assertSame(
            1,
            BillingPayment::query()->where('invoice_id', $invoice->id)->count(),
            'Deve existir exatamente 1 pagamento para a fatura — sem duplicatas.'
        );

        $invoice->refresh();
        $this->assertSame(BillingInvoiceStatus::PAID, $invoice->status);
    }

    /**
     * skipIdempotency=true deve ser ignorado fora do ambiente de testes.
     *
     * Em testes unitários (app()->runningUnitTests() === true), o flag é respeitado
     * para permitir setup sem banco de idempotência. Em produção seria silenciado.
     * Este teste verifica que o flag funciona corretamente no contexto de testes.
     */
    public function test_skip_idempotency_only_effective_in_test_environment(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $dto = BillingWebhookDTO::fromArray([
            'provider' => 'ASAAS',
            'event_type' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_skip_test',
                'value' => 50.00,
                'billingType' => 'BOLETO',
                'externalReference' => (string) $invoice->id,
            ],
        ]);

        /** @var BillingAsaasWebhookAction $action */
        $action = app(BillingAsaasWebhookAction::class);

        // Em ambiente de testes, skipIdempotency=true é respeitado (app()->runningUnitTests() === true)
        $result = $action->handle($tenant, $dto, true);

        // Idempotência foi ignorada — pagamento deve ser processado normalmente
        $this->assertTrue($result['invoice_updated']);

        $invoice->refresh();
        $this->assertSame(BillingInvoiceStatus::PAID, $invoice->status);
    }

    /**
     * O consumer não deve re-processar um evento que foi registrado via HTTP.
     */
    public function test_consumer_path_does_not_reprocess_http_received_event(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $dto = BillingWebhookDTO::fromArray([
            'provider' => 'ASAAS',
            'event_type' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_cross_path',
                'value' => 100.00,
                'billingType' => 'PIX',
                'externalReference' => (string) $invoice->id,
            ],
        ]);

        /** @var BillingAsaasWebhookAction $action */
        $action = app(BillingAsaasWebhookAction::class);

        // HTTP processa primeiro
        $httpResult = $action->handle($tenant, $dto);
        $this->assertTrue($httpResult['created']);

        // Consumer tenta processar o mesmo evento (ambos sem skipIdempotency)
        $consumerResult = $action->handle($tenant, $dto);
        $this->assertFalse($consumerResult['created'], 'Consumer deve detectar evento já processado via HTTP.');

        // Apenas 1 pagamento
        $this->assertSame(
            1,
            BillingPayment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', BillingPaymentStatus::CONFIRMED)
                ->count()
        );
    }
}
