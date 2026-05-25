<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Billing\Actions\BillingInvoiceActions;
use Domain\Billing\DTOs\BillingInvoiceDTO;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Services\BillingInvoicePdfService;
use Domain\Platform\Http\Requests\PlatformBillingInvoiceIndexRequest;
use Domain\Platform\Http\Requests\PlatformBillingInvoiceStoreRequest;
use Domain\Platform\Http\Resources\PlatformBillingInvoiceResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controller para gerenciamento de faturas na visão de plataforma (admin).
 * Permite listar, criar e cancelar faturas de qualquer tenant.
 *
 * @see BillingInvoiceActions
 */
final class PlatformBillingInvoiceController extends BaseController
{
    /**
     * @param  BillingInvoiceActions  $actions  Ação de gestão de faturas.
     */
    public function __construct(
        private readonly BillingInvoiceActions $actions
    ) {}

    /**
     * Lista todas as faturas da plataforma com filtros.
     *
     * @param  PlatformBillingInvoiceIndexRequest  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de faturas.
     */
    public function index(PlatformBillingInvoiceIndexRequest $request): JsonResponse
    {
        $this->authorize('view', BillingInvoice::class);

        $filters = $request->validated();

        $paginator = $this->actions->listForAdmin($filters);

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Domain\Billing\Models\BillingInvoice> $items */
        $items = $paginator->getCollection();
        $items->load('tenant');

        $paginator->getCollection()->transform(
            fn ($item) => new PlatformBillingInvoiceResource($item)
        );

        return $this->paginated($paginator, 'Faturas listadas');
    }

    /**
     * Cria uma nova fatura para um tenant específico.
     *
     * @param  PlatformBillingInvoiceStoreRequest  $request  Requisição com dados da fatura.
     * @return JsonResponse Fatura criada.
     */
    public function store(PlatformBillingInvoiceStoreRequest $request): JsonResponse
    {
        $this->authorize('create', BillingInvoice::class);

        $validated = $request->validated();
        $tenantId = $validated['tenant_id'];

        $dto = BillingInvoiceDTO::fromRequest($request);

        try {
            $invoice = $this->actions->create($tenantId, $dto);
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422, [
                'reference_month' => [$exception->getMessage()],
            ]);
        }

        $invoice->load('plan', 'tenant');

        return $this->created(
            new PlatformBillingInvoiceResource($invoice),
            'Fatura criada'
        );
    }

    /**
     * Cancela (soft-delete) uma fatura da plataforma.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da fatura.
     * @return JsonResponse Resposta sem conteúdo ou erro.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorize('platform.billing.invoice.delete');

        try {
            $this->actions->deleteAdmin($id);

            return $this->noContent();
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Marca uma fatura como paga manualmente.
     */
    public function markPaid(Request $request, string $id): JsonResponse
    {
        $this->authorize('platform.billing.invoice.delete');

        try {
            $invoice = $this->actions->markPaidAdmin($id);

            return $this->success(new PlatformBillingInvoiceResource($invoice), 'Fatura marcada como paga.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Baixa a fatura em PDF.
     */
    public function download(Request $request, string $id): Response
    {
        $this->authorize('platform.billing.invoice.delete');

        $invoice = $this->actions->findAdmin($id);
        $pdf = app(BillingInvoicePdfService::class)->generate($invoice);

        $filename = sprintf('fatura-%s-%s.pdf', $invoice->reference_month, substr((string) $invoice->id, 0, 8));

        return $pdf->download($filename);
    }
}
