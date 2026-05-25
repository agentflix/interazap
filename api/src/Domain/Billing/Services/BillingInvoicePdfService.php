<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Barryvdh\DomPDF\PDF;
use Domain\Billing\Models\BillingInvoice;

final class BillingInvoicePdfService
{
    public function generate(BillingInvoice $invoice): PDF
    {
        $invoice->loadMissing('plan', 'tenant');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('billing.invoice-pdf', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');
    }
}
