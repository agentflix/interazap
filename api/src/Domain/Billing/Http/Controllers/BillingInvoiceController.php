<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Actions\BillingInvoiceActions;
use Domain\Billing\DTOs\BillingInvoiceDTO;
use Domain\Billing\DTOs\BillingInvoicePaymentDTO;
use Domain\Billing\Http\Requests\BillingInvoiceIndexRequest;
use Domain\Billing\Http\Requests\BillingInvoicePayRequest;
use Domain\Billing\Http\Requests\BillingInvoiceStoreRequest;
use Domain\Billing\Http\Requests\BillingInvoiceUpdateRequest;
use Domain\Billing\Http\Resources\BillingInvoiceResource;
use Domain\Billing\Models\BillingInvoice;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Controller para gerenciamento de Faturas.
 */
final class BillingInvoiceController extends BaseController
{
    public function __construct(
        private readonly BillingInvoiceActions $actions
    ) {}

    /**
     * Lista faturas do tenant.
     */
    public function index(BillingInvoiceIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', BillingInvoice::class);

        $user = $request->user();
        $tenantId = $user?->tenant_id;
        $filters = $request->validated();
        $isAdmin = $this->userCanManageInvoices($user);

        if ($isAdmin) {
            $paginator = $this->actions->listForAdmin($filters);
        } else {
            unset($filters['tenant_id']);
            $paginator = $this->actions->list($tenantId, $filters);
        }
        $paginator->getCollection()->transform(
            fn ($item) => new BillingInvoiceResource($item)
        );

        return $this->paginated($paginator, 'Faturas listadas');
    }

    /**
     * Exibe uma fatura específica.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $invoice = $this->actions->find($tenantId, $id);
        $this->authorize('view', $invoice);

        return $this->success(
            new BillingInvoiceResource($invoice),
            'Fatura carregada'
        );
    }

    /**
     * Cria uma nova fatura.
     */
    public function store(BillingInvoiceStoreRequest $request): JsonResponse
    {
        $this->authorize('create', BillingInvoice::class);

        $tenantId = $request->user()?->tenant_id;

        try {
            $invoice = $this->actions->create(
                $tenantId,
                BillingInvoiceDTO::fromRequest($request)
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return $this->error('Já existe uma fatura para o mês informado.', 422);
        }

        return $this->created(
            new BillingInvoiceResource($invoice),
            'Fatura criada'
        );
    }

    /**
     * Atualiza uma fatura existente.
     */
    public function update(BillingInvoiceUpdateRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $invoice = $this->actions->find($tenantId, $id);
        $this->authorize('update', $invoice);

        try {
            $invoice = $this->actions->update(
                $tenantId,
                $id,
                BillingInvoiceDTO::fromRequest($request)
            );

            return $this->success(
                new BillingInvoiceResource($invoice),
                'Fatura atualizada'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Cancela uma fatura.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $invoice = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $invoice);

        try {
            $this->actions->delete($tenantId, $id);

            return $this->noContent();
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Gera cobrança no Asaas para a fatura.
     */
    public function pay(BillingInvoicePayRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $invoice = $this->actions->find($tenantId, $id);
        $this->authorize('update', $invoice);

        $paymentDto = BillingInvoicePaymentDTO::fromRequest($request);

        try {
            $result = $this->actions->pay($tenantId, $id, $paymentDto);

            $invoice = $result['invoice'];
            unset($result['invoice']);
            $payload = [
                ...$result,
                'invoice' => new BillingInvoiceResource($invoice),
            ];

            return $this->success(
                $payload,
                'Cobrança gerada com sucesso'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Obtém dados do PIX de uma fatura.
     */
    public function pix(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $invoice = $this->actions->find($tenantId, $id);
        $this->authorize('view', $invoice);

        $pix = $this->actions->getPix($tenantId, $id);

        if (! $pix['payload']) {
            return $this->error('PIX não disponível para esta fatura', 404);
        }

        return $this->success([
            'payload' => $pix['payload'],
            'qr_code' => $pix['qr_code'],
        ], 'Dados do PIX');
    }

    private function userCanManageInvoices(?AuthUser $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        try {
            if ($user->hasPermissionTo('billing.invoices.manage')) {
                return true;
            }
        } catch (PermissionDoesNotExist) {
            // Permission not configured for guard; ignore.
        }

        return $this->tokenAllowsInvoiceManagement($user);
    }

    private function tokenAllowsInvoiceManagement(AuthUser $user): bool
    {
        $token = $user->currentAccessToken();

        if ($token === null) {
            return false;
        }

        return $user->tokenCan('billing.invoices.manage') || $user->tokenCan('*');
    }
}
