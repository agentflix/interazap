<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Billing\Actions\BillingInvoiceActions;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para recibos de pagamento.
 *
 * Fornece acesso aos dados de recibo para faturas pagas.
 *
 * @category Controllers
 */
final class BillingInvoiceReceiptController extends BaseController
{
    public function __construct(private readonly BillingInvoiceActions $actions) {}

    /**
     * Obter recibo de pagamento de uma fatura.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID da fatura.
     * @return JsonResponse Dados do recibo (pagador, valor, metodo, data).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        try {
            $receipt = $this->actions->getReceipt($tenantId, $id);

            return $this->success($receipt, 'Recibo gerado');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
