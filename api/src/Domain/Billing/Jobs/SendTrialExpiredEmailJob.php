<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Domain\Billing\Mail\SendTrialExpiredMail;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job que envia email de notificação de trial expirado para o tenant.
 *
 * Dispatched by `CloseExpiredTrialJob`. Idempotência garantida pelo caller.
 */
final class SendTrialExpiredEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $tenantId) {}

    /**
     * Envia o email de trial expirado para o email primário do tenant.
     */
    public function handle(): void
    {
        $tenant = PlatformTenant::query()
            ->find($this->tenantId);

        if (! $tenant) {
            Log::warning('billing.trial.expired_email.tenant_not_found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        // Send to primary email
        $email = $tenant->primary_email;
        if (! $email) {
            return;
        }

        Mail::to($email)->send(new SendTrialExpiredMail($tenant));

        Log::info('billing.trial.expired_email.sent', [
            'tenant_id' => $this->tenantId,
            'email' => $email,
        ]);
    }
}
