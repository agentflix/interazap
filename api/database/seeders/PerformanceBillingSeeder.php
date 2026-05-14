<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;

/**
 * Performance seed for Billing context.
 *
 * Seeds invoices, payments, collection logs, and purge reports.
 * Total: ~1,505 records across 50 tenants.
 */
final class PerformanceBillingSeeder
{
    use WithoutModelEvents;

    private const int BATCH_SIZE = 500;

    public function seedForTenant(string $tenantId): void
    {
        $invoiceIds = $this->seedInvoices($tenantId);
        $this->seedPayments($tenantId, $invoiceIds);
        $this->seedCollectionLogs($tenantId, $invoiceIds);
    }

    /** @return array<int, string> Invoice IDs */
    private function seedInvoices(string $tenantId): array
    {
        $statusWeights = ['draft' => 10, 'pending' => 30, 'paid' => 45, 'overdue' => 10, 'cancelled' => 5];
        $methods = ['pix' => 50, 'credit_card' => 30, 'boleto' => 15, 'bank_transfer' => 5];
        $planIds = DB::table('platform_plans')->where('is_active', true)->pluck('id')->toArray();

        $invoices = [];
        $ids = [];

        // 12 invoices per tenant (1 per month)
        for ($i = 0; $i < 12; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $referenceMonth = now()->subMonths($i)->format('Y-m');
            $dueDate = now()->subMonths($i)->copy()->addDays(random_int(5, 30));
            $isPaid = $status === 'paid';

            $invoices[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'plan_id' => $planIds[array_rand($planIds)] ?? null,
                'reference_month' => $referenceMonth,
                'amount' => random_int(100, 5000) + (random_int(0, 99) / 100),
                'status' => $status,
                'due_date' => $dueDate,
                'paid_at' => $isPaid ? $dueDate->copy()->addDays(random_int(1, 5)) : null,
                'payment_method' => $isPaid ? PerformanceSeeder::weightedRandom($methods) : null,
                'payment_url' => $status === 'pending' || $status === 'overdue' ? 'https://asaas.perf.local/pay/'.random_int(1000, 9999) : null,
                'asaas_payment_id' => (string) \Illuminate\Support\Str::uuid(),
                'pix_payload' => $isPaid ? '00020126580014BR.GOV.BCB.PIX...' : null,
                'pix_qr_code_base64' => $isPaid ? base64_encode(random_bytes(100)) : null,
                'metadata' => json_encode(['cycle' => $i + 1]),
                'created_at' => now()->subMonths($i),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('billing_invoices', $invoices, self::BATCH_SIZE);

        return $ids;
    }

    private function seedPayments(string $tenantId, array $invoiceIds): void
    {
        $statusWeights = ['confirmed' => 80, 'pending' => 10, 'failed' => 7, 'refunded' => 3];
        $methods = ['pix' => 50, 'credit_card' => 30, 'boleto' => 15, 'bank_transfer' => 5];
        $payments = [];

        // Payments for ~60% of invoices
        $paymentCount = (int) (count($invoiceIds) * 0.6);
        $selectedInvoices = array_slice($invoiceIds, 0, $paymentCount);

        foreach ($selectedInvoices as $invoiceId) {
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $amount = random_int(100, 5000) + (random_int(0, 99) / 100);

            $payments[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_method' => PerformanceSeeder::weightedRandom($methods),
                'provider' => 'asaas',
                'provider_payment_id' => 'pay_'.random_int(100000, 999999),
                'status' => $status,
                'confirmed_at' => $status === 'confirmed' ? now()->subDays(random_int(1, 30)) : null,
                'metadata' => json_encode(['gateway_fee' => $amount * 0.02]),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if ($payments !== []) {
            PerformanceSeeder::insertBatch('billing_payments', $payments, self::BATCH_SIZE);
        }
    }

    private function seedCollectionLogs(string $tenantId, array $invoiceIds): void
    {
        $channels = ['email' => 60, 'whatsapp' => 30, 'sms' => 10];
        $statuses = ['sent' => 70, 'delivered' => 20, 'failed' => 10];
        $templates = ['cobranca_vencida', 'lembrete_pagamento', 'notificacao_atraso', 'ultimo_aviso'];
        $logs = [];
        $count = random_int(8, 12);

        for ($i = 0; $i < $count; $i++) {
            $status = PerformanceSeeder::weightedRandom($statuses);
            $channel = PerformanceSeeder::weightedRandom($channels);

            $logs[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'invoice_id' => $invoiceIds[array_rand($invoiceIds)],
                'template_id' => $templates[array_rand($templates)],
                'channel' => $channel,
                'recipient' => $channel === 'email' ? 'billing@perf.local' : '+55'.random_int(1100000000, 99999999999),
                'status' => $status,
                'provider_message_id' => (string) \Illuminate\Support\Str::uuid(),
                'metadata' => json_encode(['attempt' => random_int(1, 3)]),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('billing_collection_logs', $logs, self::BATCH_SIZE);
    }
}
