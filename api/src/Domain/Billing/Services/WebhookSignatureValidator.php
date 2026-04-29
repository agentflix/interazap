<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Illuminate\Http\Request;

/**
 * Valida assinatura/token de provedores de webhook de billing.
 */
final class WebhookSignatureValidator
{
    private const string PROVIDER_ASAAS = 'asaas';

    /**
     * Valida a assinatura/token do webhook para o provider informado.
     */
    public function validate(Request $request, string $provider): bool
    {
        return match (strtolower($provider)) {
            self::PROVIDER_ASAAS => $this->validateAsaas($request),
            default => false,
        };
    }

    /**
     * Valida o token do webhook Asaas.
     *
     * Quando o token não está configurado, mantém compatibilidade retornando true.
     */
    private function validateAsaas(Request $request): bool
    {
        $expectedToken = (string) config('services.asaas.webhook_token', '');

        if ($expectedToken === '') {
            return true;
        }

        $receivedToken = (string) (
            $request->header('asaas-access-token')
            ?? $request->header('x-asaas-access-token')
            ?? ''
        );

        if ($receivedToken === '') {
            return false;
        }

        return hash_equals($expectedToken, $receivedToken);
    }
}
