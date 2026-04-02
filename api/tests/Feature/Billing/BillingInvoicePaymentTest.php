<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingInvoicePaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pix_payment_generates_qr_code(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenant->forceFill([
            'asaas_customer_id' => 'cus_123',
            'document' => '12345678901',
        ])->save();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::DRAFT->value,
            'amount' => 99.0,
            'due_date' => now()->toDateString(),
        ]);

        config(['services.gateway.url' => 'http://gateway.test']);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/internal/billing/payments/pay_1/pix')) {
                return Http::response([
                    'payload' => '00020126',
                    'encodedImage' => 'BASE64',
                    'expirationDate' => now()->addHour()->toISOString(),
                ], 200);
            }

            if (str_contains($url, '/internal/billing/payments')) {
                return Http::response(['id' => 'pay_1', 'invoiceUrl' => 'http://pay', 'status' => 'PENDING'], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user, abilities: ['*']);

        $response = $this->postJson("/api/billing/invoices/{$invoice->id}/pay", [
            'method' => 'PIX',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.method', 'PIX')
            ->assertJsonPath('data.pix_copy_paste', '00020126');
    }

    public function test_credit_card_payment_processes(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenant->forceFill([
            'asaas_customer_id' => 'cus_123',
            'document' => '12345678901',
        ])->save();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::DRAFT->value,
            'amount' => 120.0,
            'due_date' => now()->toDateString(),
        ]);

        config(['services.gateway.url' => 'http://gateway.test']);

        Http::fake([
            'http://gateway.test/internal/billing/payments' => Http::response(['id' => 'pay_2', 'invoiceUrl' => 'http://pay', 'status' => 'CONFIRMED'], 200),
        ]);

        Sanctum::actingAs($user, abilities: ['*']);

        $response = $this->postJson("/api/billing/invoices/{$invoice->id}/pay", [
            'method' => 'CREDIT_CARD',
            'card' => [
                'holder_name' => 'João Silva',
                'number' => '4111111111111111',
                'expiry_month' => '12',
                'expiry_year' => '2028',
                'cvv' => '123',
            ],
            'holder_info' => [
                'name' => 'João Silva',
                'email' => 'joao@example.com',
                'cpf_cnpj' => '12345678901',
                'postal_code' => '01310100',
                'address_number' => '123',
                'phone' => '11999999999',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.method', 'CREDIT_CARD')
            ->assertJsonPath('data.status', 'CONFIRMED');
    }

    public function test_receipt_available_after_payment_confirmed(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenant->forceFill([
            'asaas_customer_id' => 'cus_123',
            'document' => '12345678901',
        ])->save();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PAID->value,
            'paid_at' => now(),
        ]);

        BillingPayment::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'amount' => 120.0,
            'payment_method' => 'pix',
            'provider' => 'asaas',
            'provider_payment_id' => 'pay_3',
            'status' => BillingPaymentStatus::CONFIRMED->value,
            'confirmed_at' => now(),
            'metadata' => null,
        ]);

        Sanctum::actingAs($user, abilities: ['*']);

        $response = $this->getJson("/api/billing/invoices/{$invoice->id}/receipt");

        $response->assertOk()
            ->assertJsonPath('data.invoice_id', $invoice->id);
    }
}
