<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\DTOs\BillingInvoiceDTO;
use Domain\Billing\DTOs\BillingInvoicePaymentDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Configuration\Events\BillingInvoiceCreatedEvent;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Scopes\TenantScope;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Actions para operações de Faturas.
 */
final class BillingInvoiceActions
{
    public function __construct(
        private readonly BillingGatewayService $gatewayService,
    ) {}

    /**
     * Lista faturas do tenant com paginação.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, BillingInvoice>
     */
    public function list(?string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = BillingInvoice::query()
            ->where('tenant_id', $tenantId)
            ->with('plan');

        $this->applyFilters($query, $filters);

        return $query
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Lista faturas para visão administrativa.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, BillingInvoice>
     */
    public function listForAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = BillingInvoice::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('plan');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        $this->applyFilters($query, $filters);

        return $query
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Busca uma fatura por ID.
     */
    public function find(?string $tenantId, string $id): BillingInvoice
    {
        return BillingInvoice::query()
            ->where('tenant_id', $tenantId)
            ->with('plan', 'payments')
            ->findOrFail($id);
    }

    /**
     * Busca uma fatura por ID sem escopo de tenant (visão admin).
     */
    public function findAdmin(string $id): BillingInvoice
    {
        return BillingInvoice::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('plan', 'payments', 'tenant')
            ->findOrFail($id);
    }

    /**
     * Cancela uma fatura sem escopo de tenant (visão admin).
     *
     * @throws \DomainException Quando a fatura já está paga.
     */
    public function deleteAdmin(string $id): void
    {
        $invoice = $this->findAdmin($id);

        if ($invoice->status === BillingInvoiceStatus::PAID) {
            throw new \DomainException('Não é possível cancelar uma fatura já paga.');
        }

        $invoice->update(['status' => BillingInvoiceStatus::CANCELLED]);
    }

    /**
     * Cria uma nova fatura.
     */
    public function create(?string $tenantId, BillingInvoiceDTO $dto): BillingInvoice
    {
        $invoice = BillingInvoice::create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);

        if (is_string($tenantId) && $tenantId !== '') {
            BillingInvoiceCreatedEvent::dispatch(
                $tenantId,
                (string) $invoice->id,
                (float) $invoice->amount,
                (string) $invoice->reference_month,
            );
        }

        return $invoice;
    }

    /**
     * Atualiza uma fatura existente.
     */
    public function update(?string $tenantId, string $id, BillingInvoiceDTO $dto): BillingInvoice
    {
        $invoice = $this->find($tenantId, $id);

        // Não permite editar faturas pagas ou canceladas
        if (in_array($invoice->status, [BillingInvoiceStatus::PAID, BillingInvoiceStatus::CANCELLED], true)) {
            throw new \DomainException('Não é possível editar uma fatura paga ou cancelada.');
        }

        $invoice->update($dto->toArray());

        return $invoice->refresh();
    }

    /**
     * Cancela uma fatura.
     */
    public function delete(?string $tenantId, string $id): void
    {
        $invoice = $this->find($tenantId, $id);

        // Não permite cancelar faturas pagas
        if ($invoice->status === BillingInvoiceStatus::PAID) {
            throw new \DomainException('Não é possível cancelar uma fatura já paga.');
        }

        $invoice->update(['status' => BillingInvoiceStatus::CANCELLED]);
    }

    /**
     * Gera cobrança no Asaas para a fatura.
     *
     * @param  string  $paymentMethod  'pix' ou 'credit_card'
     */
    /**
     * @return array{method:string,invoice:BillingInvoice,qr_code_base64?:string|null,pix_copy_paste?:string|null,expires_at?:string|null,status?:string|null}
     */
    public function pay(?string $tenantId, string $id, BillingInvoicePaymentDTO $paymentDto): array
    {
        $invoice = $this->find($tenantId, $id);

        if (! $invoice->canBePaid()) {
            throw new \DomainException('Esta fatura não pode ser paga.');
        }

        /** @var PlatformTenant $tenant */
        $tenant = PlatformTenant::findOrFail($tenantId);

        // Garante que o tenant tem um cliente no Asaas
        $customerId = $this->gatewayService->ensureCustomer($tenant);

        if (! $customerId) {
            throw new \DomainException('Não foi possível criar o cliente no Asaas. Verifique o documento do tenant.');
        }

        $method = strtoupper($paymentDto->method);

        $payment = $this->gatewayService->createPayment(
            customerId: $customerId,
            value: (float) $invoice->amount,
            dueDate: $invoice->due_date->format('Y-m-d'),
            description: "Fatura {$invoice->reference_month}",
            externalReference: $invoice->id,
            billingType: $method,
            card: $paymentDto->card,
            holderInfo: $paymentDto->holderInfo
        );

        if (! $payment['id']) {
            throw new \DomainException('Não foi possível criar a cobrança no Asaas.');
        }

        // Atualiza a fatura com os dados da cobrança
        $invoice->update([
            'status' => BillingInvoiceStatus::PENDING,
            'payment_method' => strtolower($method),
            'payment_url' => $payment['invoiceUrl'],
            'asaas_payment_id' => $payment['id'],
        ]);

        // Se for PIX, busca o QR Code
        if ($method === 'PIX') {
            $pix = $this->gatewayService->getPixQRCode($payment['id']);

            if ($pix['payload']) {
                $invoice->update([
                    'pix_payload' => $pix['payload'],
                    'pix_qr_code_base64' => $pix['encodedImage'],
                ]);
            }

            return [
                'method' => 'PIX',
                'invoice' => $invoice->refresh(),
                'qr_code_base64' => $pix['encodedImage'] ? 'data:image/png;base64,'.$pix['encodedImage'] : null,
                'pix_copy_paste' => $pix['payload'],
                'expires_at' => $pix['expirationDate'] ?? null,
            ];
        }

        return [
            'method' => 'CREDIT_CARD',
            'invoice' => $invoice->refresh(),
            'status' => $payment['status'],
        ];
    }

    /**
     * Obtém dados do PIX de uma fatura.
     *
     * @return array{payload: string|null, qr_code: string|null}
     */
    public function getPix(?string $tenantId, string $id): array
    {
        $invoice = $this->find($tenantId, $id);

        // Se já tem PIX salvo, retorna
        if ($invoice->pix_payload) {
            return [
                'payload' => $invoice->pix_payload,
                'qr_code' => $invoice->pix_qr_code_base64,
            ];
        }

        // Se tem payment_id mas não tem PIX, busca novamente
        if ($invoice->asaas_payment_id && $invoice->payment_method === 'pix') {
            $pix = $this->gatewayService->getPixQRCode($invoice->asaas_payment_id);

            if ($pix['payload']) {
                $invoice->update([
                    'pix_payload' => $pix['payload'],
                    'pix_qr_code_base64' => $pix['encodedImage'],
                ]);

                return [
                    'payload' => $pix['payload'],
                    'qr_code' => $pix['encodedImage'],
                ];
            }
        }

        return ['payload' => null, 'qr_code' => null];
    }

    /**
     * Gera dados do recibo de pagamento.
     *
     * @return array<string, mixed>
     */
    public function getReceipt(?string $tenantId, string $id): array
    {
        $invoice = BillingInvoice::query()
            ->where('tenant_id', $tenantId)
            ->with(['tenant', 'plan', 'payments'])
            ->findOrFail($id);

        if ($invoice->status !== BillingInvoiceStatus::PAID) {
            throw new \DomainException('Recibo disponível apenas para faturas pagas.');
        }

        $payment = $invoice->payments()
            ->where('status', BillingPaymentStatus::CONFIRMED)
            ->orderByDesc('confirmed_at')
            ->first();

        /** @var BillingPayment|null $payment */
        $tenant = $invoice->tenant;

        return [
            'invoice_id' => $invoice->id,
            'reference_month' => $invoice->reference_month,
            'amount' => (float) $invoice->amount,
            'paid_at' => $payment?->confirmed_at?->toISOString() ?? $invoice->paid_at?->toISOString(),
            'payment_method' => $payment ? $payment->payment_method : $invoice->payment_method,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->primary_email,
            ],
            'plan' => $invoice->plan ? [
                'id' => $invoice->plan->id,
                'name' => $invoice->plan->name,
            ] : null,
        ];
    }

    /**
     * @param  Builder<BillingInvoice>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters = []): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['reference_month'])) {
            $query->where('reference_month', $filters['reference_month']);
        }

        if (! empty($filters['search'])) {
            $query->where('reference_month', 'like', SearchSanitizer::likeContains($filters['search']));
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }

        if (! empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }
    }
}
