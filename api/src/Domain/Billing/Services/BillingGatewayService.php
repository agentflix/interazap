<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço para operações de Billing via Gateway.
 */
class BillingGatewayService
{
    private string $gatewayUrl;

    private ?string $apiKey;

    private int $timeout;

    private ?string $lastError = null;

    public function __construct()
    {
        $this->gatewayUrl = rtrim((string) config('services.gateway.url', 'http://gateway:3000'), '/');
        $this->apiKey = config('services.gateway.api_key');
        $this->timeout = (int) config('services.gateway.timeout', 5);
    }

    /** Retorna o último erro registrado pela chamada HTTP mais recente ao gateway. */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Cria ou retorna o ID do cliente no Asaas via Gateway.
     */
    public function ensureCustomer(PlatformTenant $tenant): ?string
    {
        if (! empty($tenant->asaas_customer_id)) {
            $this->lastError = null;

            return $tenant->asaas_customer_id;
        }

        $document = $tenant->document ?? null;
        if (empty($document)) {
            $this->lastError = 'É necessário cadastrar o CPF ou CNPJ da empresa para gerar cobranças. Acesse Configurações > Empresa > Dados Cadastrais.';

            Log::warning('Gateway Billing: Tenant sem documento, não é possível criar cliente', [
                'tenant_id' => $tenant->id,
            ]);

            return null;
        }

        try {
            $response = $this->client()
                ->post("{$this->gatewayUrl}/internal/billing/customers", [
                    'name' => $tenant->name,
                    'cpfCnpj' => preg_replace('/\D/', '', (string) $document),
                    'email' => $tenant->primary_email,
                    'externalReference' => $tenant->id,
                ]);

            if ($response->failed()) {
                $this->lastError = 'falha HTTP ao criar customer: '.$response->status().' '.$this->safeResponseBody($response->body());

                Log::error('Gateway Billing: Falha ao criar cliente', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $customerId = $response->json('id');

            if ($customerId) {
                $tenant->forceFill(['asaas_customer_id' => $customerId])->save();
                $this->lastError = null;

                return $customerId;
            }

            $this->lastError = 'gateway respondeu sem id de customer';

            return null;
        } catch (\Throwable $e) {
            $this->lastError = 'exceção ao criar customer: '.$e->getMessage();

            Log::error('Gateway Billing: Exceção ao criar cliente', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cria uma cobrança no Asaas via gateway para o cliente informado.
     *
     * Suporta PIX e cartão de crédito. Retorna o ID da cobrança e URL da fatura.
     *
     * @param  array<string, string>|null  $card  Dados do cartão (obrigatório para CREDIT_CARD)
     * @param  array<string, string>|null  $holderInfo  Dados do titular (obrigatório para CREDIT_CARD)
     * @return array{id: string|null, invoiceUrl: string|null, status: string|null}
     */
    public function createPayment(
        string $customerId,
        float $value,
        string $dueDate,
        string $description,
        string $externalReference,
        string $billingType = 'PIX',
        ?array $card = null,
        ?array $holderInfo = null
    ): array {
        try {
            $payload = [
                'customer' => $customerId,
                'billingType' => $billingType,
                'value' => $value,
                'dueDate' => $dueDate,
                'description' => $description,
                'externalReference' => $externalReference,
            ];

            if ($billingType === 'CREDIT_CARD' && $card && $holderInfo) {
                $payload['creditCard'] = [
                    'holderName' => $card['holder_name'] ?? '',
                    'number' => $card['number'] ?? '',
                    'expiryMonth' => $card['expiry_month'] ?? '',
                    'expiryYear' => $card['expiry_year'] ?? '',
                    'ccv' => $card['cvv'] ?? '',
                ];
                $payload['creditCardHolderInfo'] = [
                    'name' => $holderInfo['name'] ?? '',
                    'email' => $holderInfo['email'] ?? '',
                    'cpfCnpj' => preg_replace('/\D/', '', $holderInfo['cpf_cnpj'] ?? ''),
                    'postalCode' => $holderInfo['postal_code'] ?? '',
                    'addressNumber' => $holderInfo['address_number'] ?? '',
                    'phone' => $holderInfo['phone'] ?? '',
                ];
            }

            $response = $this->client()
                ->post("{$this->gatewayUrl}/internal/billing/payments", $payload);

            if ($response->failed()) {
                $this->lastError = 'falha HTTP ao criar cobrança: '.$response->status().' '.$this->safeResponseBody($response->body());

                Log::error('Gateway Billing: Falha ao criar cobrança', [
                    'customer_id' => $customerId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['id' => null, 'invoiceUrl' => null, 'status' => null];
            }

            $result = [
                'id' => $response->json('id'),
                'invoiceUrl' => $response->json('invoiceUrl'),
                'status' => $response->json('status'),
            ];

            if (empty($result['id'])) {
                $this->lastError = 'gateway respondeu sem id de cobrança';
            } else {
                $this->lastError = null;
            }

            return $result;
        } catch (\Throwable $e) {
            $this->lastError = 'exceção ao criar cobrança: '.$e->getMessage();

            Log::error('Gateway Billing: Exceção ao criar cobrança', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return ['id' => null, 'invoiceUrl' => null, 'status' => null];
        }
    }

    /**
     * Obtém o QR Code PIX e o payload copia-e-cola de uma cobrança.
     *
     * @return array{payload: string|null, encodedImage: string|null, expirationDate: string|null}
     */
    public function getPixQRCode(string $paymentId): array
    {
        try {
            $response = $this->client()
                ->get("{$this->gatewayUrl}/internal/billing/payments/{$paymentId}/pix");

            if ($response->failed()) {
                $this->lastError = 'falha HTTP ao obter PIX: '.$response->status().' '.$this->safeResponseBody($response->body());

                Log::error('Gateway Billing: Falha ao obter QR Code PIX', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['payload' => null, 'encodedImage' => null, 'expirationDate' => null];
            }

            $this->lastError = null;

            return [
                'payload' => $response->json('payload'),
                'encodedImage' => $response->json('encodedImage'),
                'expirationDate' => $response->json('expirationDate'),
            ];
        } catch (\Throwable $e) {
            $this->lastError = 'exceção ao obter PIX: '.$e->getMessage();

            Log::error('Gateway Billing: Exceção ao obter QR Code PIX', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return ['payload' => null, 'encodedImage' => null, 'expirationDate' => null];
        }
    }

    /**
     * Consulta o status de uma cobrança pelo ID do Asaas.
     *
     * @return array{status: string|null, value: float|null, confirmedDate: string|null}
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = $this->client()
                ->get("{$this->gatewayUrl}/internal/billing/payments/{$paymentId}/status");

            if ($response->failed()) {
                $this->lastError = 'falha HTTP ao consultar status: '.$response->status().' '.$this->safeResponseBody($response->body());

                return ['status' => null, 'value' => null, 'confirmedDate' => null];
            }

            $this->lastError = null;

            return [
                'status' => $response->json('status'),
                'value' => $response->json('value'),
                'confirmedDate' => $response->json('confirmedDate'),
            ];
        } catch (\Throwable $e) {
            $this->lastError = 'exceção ao consultar status: '.$e->getMessage();

            Log::error('Gateway Billing: Exceção ao consultar status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return ['status' => null, 'value' => null, 'confirmedDate' => null];
        }
    }

    /** Cria um produto no gateway para o plano informado. Retorna o ID do produto ou null em caso de falha. */
    public function createProduct(PlatformPlan $plan): ?string
    {
        try {
            $response = $this->client()
                ->post("{$this->gatewayUrl}/internal/platform/products", [
                    'name' => $plan->name,
                    'description' => "Plano {$plan->name}",
                    'value' => (float) $plan->price_monthly,
                    'externalReference' => $plan->id,
                ]);

            if ($response->failed()) {
                Log::error('Gateway Billing: Falha ao criar produto do plano', [
                    'plan_id' => $plan->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('id');
        } catch (\Throwable $e) {
            Log::error('Gateway Billing: Exceção ao criar produto do plano', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Atualiza o produto no gateway com nome e valor do plano. Retorna false se não houver ID de produto. */
    public function updateProduct(PlatformPlan $plan): bool
    {
        if (! $plan->asaas_product_id) {
            return false;
        }

        try {
            $response = $this->client()
                ->post("{$this->gatewayUrl}/internal/platform/products/{$plan->asaas_product_id}", [
                    'name' => $plan->name,
                    'description' => "Plano {$plan->name}",
                    'value' => (float) $plan->price_monthly,
                ]);

            if ($response->failed()) {
                Log::warning('Gateway Billing: Falha ao atualizar produto do plano', [
                    'plan_id' => $plan->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Gateway Billing: Exceção ao atualizar produto do plano', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Envia mensagem de cobrança via WhatsApp pelo gateway.
     *
     * @param  array<string, scalar|null>  $variables  Variáveis do template (ex: nome, valor, vencimento)
     * @return array{success: bool, status_code: int, message_id: string|null}
     */
    public function sendCollectionMessage(
        string $tenantId,
        string $phone,
        string $templateId,
        array $variables,
    ): array {
        try {
            $response = $this->client()->post("{$this->gatewayUrl}/internal/billing/collection/send", [
                'tenantId' => $tenantId,
                'phone' => $phone,
                'templateId' => $templateId,
                'variables' => $variables,
            ]);

            if ($response->failed()) {
                Log::warning('Gateway Billing: Falha ao enviar cobrança por WhatsApp', [
                    'tenant_id' => $tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'status_code' => $response->status(),
                    'message_id' => null,
                ];
            }

            return [
                'success' => (bool) $response->json('success', true),
                'status_code' => $response->status(),
                'message_id' => $response->json('messageId'),
            ];
        } catch (\Throwable $e) {
            Log::error('Gateway Billing: Exceção no envio de cobrança por WhatsApp', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'message_id' => null,
            ];
        }
    }

    /** Cria um cliente HTTP configurado com timeout e header de autenticação. */
    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders($this->apiKey ? ['X-API-Key' => $this->apiKey] : []);
    }

    /** Sanitiza e trunca o corpo da resposta HTTP para uso seguro em logs. */
    private function safeResponseBody(string $body): string
    {
        $sanitized = trim(preg_replace('/\s+/', ' ', $body) ?? '');

        return substr($sanitized, 0, 240);
    }

    /**
     * Cria cobrança usando token de cartão salvo (recorrência sem PAN).
     *
     * @param  array<string, mixed>  $metadata
     * @return array{paymentId: string|null, status: string|null, brand: string|null, last4: string|null}
     */
    public function createPaymentWithToken(
        string $customerId,
        string $cardToken,
        float $amount,
        array $metadata = []
    ): array {
        try {
            $payload = [
                'customer' => $customerId,
                'billingType' => 'CREDIT_CARD',
                'value' => $amount,
                'dueDate' => now()->toDateString(),
                'description' => $metadata['description'] ?? 'Assinatura InteraZap',
                'externalReference' => $metadata['external_reference'] ?? '',
                'creditCardToken' => $cardToken,
            ];

            $response = $this->client()
                ->post("{$this->gatewayUrl}/internal/billing/payments", $payload);

            if ($response->failed()) {
                $this->lastError = 'falha HTTP ao criar cobrança com token: '.$response->status().' '.$this->safeResponseBody($response->body());

                Log::error('Gateway Billing: Falha ao criar cobrança com token', [
                    'customer_id' => $customerId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['paymentId' => null, 'status' => null, 'brand' => null, 'last4' => null];
            }

            $this->lastError = null;

            return [
                'paymentId' => $response->json('id'),
                'status' => $response->json('status'),
                'brand' => $response->json('creditCard.creditCardBrand'),
                'last4' => $response->json('creditCard.creditCardNumber'),
            ];
        } catch (\Throwable $e) {
            $this->lastError = 'exceção ao criar cobrança com token: '.$e->getMessage();

            Log::error('Gateway Billing: Exceção ao criar cobrança com token', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return ['paymentId' => null, 'status' => null, 'brand' => null, 'last4' => null];
        }
    }

    /**
     * Valida token de cartão no gateway e retorna metadados sem cobrar.
     *
     * @return array{brand: string|null, last4: string|null}
     */
    public function getPaymentMethod(string $customerId, string $cardToken): array
    {
        try {
            $response = $this->client()
                ->post("{$this->gatewayUrl}/internal/billing/payment-methods", [
                    'customer_id' => $customerId,
                    'token' => $cardToken,
                ]);

            if ($response->failed()) {
                $this->lastError = 'falha HTTP ao validar método de pagamento: '.$response->status().' '.$this->safeResponseBody($response->body());

                return ['brand' => null, 'last4' => null];
            }

            $this->lastError = null;

            return [
                'brand' => $response->json('brand'),
                'last4' => $response->json('last4'),
            ];
        } catch (\Throwable $e) {
            $this->lastError = 'exceção ao validar método de pagamento: '.$e->getMessage();

            return ['brand' => null, 'last4' => null];
        }
    }
}
