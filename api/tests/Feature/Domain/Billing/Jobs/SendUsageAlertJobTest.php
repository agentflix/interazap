<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing\Jobs;

use Domain\Billing\Jobs\SendUsageAlertJob;
use Domain\Billing\Mail\UsageAlertMail;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendUsageAlertJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeGatewayMock(bool $success = true): BillingGatewayService
    {
        $mock = $this->createMock(BillingGatewayService::class);
        $mock->method('sendCollectionMessage')->willReturn([
            'success' => $success,
            'status_code' => $success ? 200 : 500,
            'message_id' => $success ? 'msg-123' : null,
        ]);

        return $mock;
    }

    #[Test]
    public function it_sends_email_alert(): void
    {
        Mail::fake();

        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 100]);
        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
            'primary_email' => 'test@example.com',
        ]);

        $job = new SendUsageAlertJob(
            tenantId: $tenant->id,
            threshold: 80,
            current: 80,
            limit: 100,
            mode: 'stop',
            overagePrice: null,
        );
        $job->handle($this->makeGatewayMock());

        Mail::assertSent(UsageAlertMail::class);
    }

    #[Test]
    public function it_sends_whatsapp_alert_when_phone_present(): void
    {
        Mail::fake();

        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 100]);
        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
            'primary_email' => 'test@example.com',
            'phone' => '+5511999999999',
        ]);

        $gateway = $this->createMock(BillingGatewayService::class);
        $gateway->expects($this->once())
            ->method('sendCollectionMessage')
            ->with(
                tenantId: $tenant->id,
                phone: '+5511999999999',
                templateId: 'billing_usage_alert_80',
                variables: $this->arrayHasKey('current'),
            )
            ->willReturn(['success' => true, 'status_code' => 200, 'message_id' => 'msg-1']);

        $job = new SendUsageAlertJob(
            tenantId: $tenant->id,
            threshold: 80,
            current: 80,
            limit: 100,
            mode: 'stop',
            overagePrice: null,
        );
        $job->handle($gateway);

        Mail::assertSent(UsageAlertMail::class);
    }

    #[Test]
    public function it_uses_100_template_for_full_limit(): void
    {
        Mail::fake();

        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 100]);
        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
            'primary_email' => 'test@example.com',
            'phone' => '+5511999999999',
        ]);

        $gateway = $this->createMock(BillingGatewayService::class);
        $gateway->expects($this->once())
            ->method('sendCollectionMessage')
            ->with(
                tenantId: $tenant->id,
                phone: '+5511999999999',
                templateId: 'billing_usage_alert_100',
                variables: $this->arrayHasKey('current'),
            )
            ->willReturn(['success' => true, 'status_code' => 200, 'message_id' => 'msg-2']);

        $job = new SendUsageAlertJob(
            tenantId: $tenant->id,
            threshold: 100,
            current: 100,
            limit: 100,
            mode: 'stop',
            overagePrice: null,
        );
        $job->handle($gateway);
    }
}
