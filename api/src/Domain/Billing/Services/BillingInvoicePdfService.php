<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Barryvdh\DomPDF\PDF;
use Domain\Billing\Models\BillingInvoice;

/**
 * Serviço para geração de PDF de fatura usando DomPDF.
 */
final class BillingInvoicePdfService
{
    /**
     * Gera o PDF da fatura em formato A4 retrato.
     *
     * @param  BillingInvoice  $invoice  Fatura com relacionamentos plan e tenant
     * @return PDF Instância DomPDF pronta para download ou stream
     */
    public function generate(BillingInvoice $invoice): PDF
    {
        $invoice->loadMissing('plan', 'tenant');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('billing.invoice-pdf', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');
    }
}
