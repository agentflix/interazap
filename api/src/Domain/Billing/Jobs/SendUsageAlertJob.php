<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Domain\Billing\Mail\UsageAlertMail;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job that sends usage threshold alert emails to the tenant.
 */
final class SendUsageAlertJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $threshold,
        public readonly int $current,
        public readonly int $limit,
        public readonly string $mode,
        public readonly ?string $overagePrice,
    ) {}

    public function handle(BillingGatewayService $gatewayService): void
    {
        $tenant = PlatformTenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('[SendUsageAlertJob] Tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        try {
            $this->sendEmail($tenant);
        } catch (\Throwable) {
            // already logged inside sendEmail; continue to WhatsApp
        }

        $this->sendWhatsApp($tenant, $gatewayService);
    }

    private function sendEmail(PlatformTenant $tenant): void
    {
        $email = $tenant->primary_email;

        if (! is_string($email) || $email === '') {
            Log::warning('[SendUsageAlertJob] Tenant has no primary_email', ['tenant_id' => $this->tenantId]);

            return;
        }

        try {
            Mail::to($email)->send(new UsageAlertMail(
                tenantName: (string) $tenant->name,
                threshold: $this->threshold,
                current: $this->current,
                limit: $this->limit,
                mode: $this->mode,
                overagePrice: $this->overagePrice,
            ));

            Log::info('[SendUsageAlertJob] Usage alert email sent', [
                'tenant_id' => $this->tenantId,
                'threshold' => $this->threshold,
                'email' => $email,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SendUsageAlertJob] Failed to send usage alert email', [
                'tenant_id' => $this->tenantId,
                'threshold' => $this->threshold,
                'error' => $e->getMessage(),
            ]);

            throw $e; // re-thrown so caller can decide to retry
        }
    }

    private function sendWhatsApp(PlatformTenant $tenant, BillingGatewayService $gatewayService): void
    {
        $phone = $tenant->phone;

        if (! is_string($phone) || $phone === '') {
            return;
        }

        $templateId = $this->threshold >= 100
            ? 'billing_usage_alert_100'
            : 'billing_usage_alert_80';

        $result = $gatewayService->sendCollectionMessage(
            tenantId: $this->tenantId,
            phone: $phone,
            templateId: $templateId,
            variables: [
                'tenant_name' => (string) $tenant->name,
                'current' => $this->current,
                'limit' => $this->limit,
                'mode' => $this->mode,
            ],
        );

        if (! $result['success']) {
            Log::warning('[SendUsageAlertJob] WhatsApp alert failed', [
                'tenant_id' => $this->tenantId,
                'threshold' => $this->threshold,
                'status_code' => $result['status_code'] ?? null,
            ]);
        } else {
            Log::info('[SendUsageAlertJob] Usage alert WhatsApp sent', [
                'tenant_id' => $this->tenantId,
                'threshold' => $this->threshold,
            ]);
        }
    }
}
