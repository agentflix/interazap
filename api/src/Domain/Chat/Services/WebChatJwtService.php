<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Illuminate\Support\Facades\Log;

/**
 * JWT-like service for WebChat session tokens.
 *
 * Gera e valida tokens signed para identificar sessões webchat,
 * contendo claims: session_id, tenant_id, contact_id, ticket_id.
 *
 * Implementação própria usando HMAC-SHA256 sem dependência externa.
 *
 * @category Services
 */
final class WebChatJwtService
{
    private const ALGORITHM = 'sha256';

    private const TTL_SECONDS = 86400; // 24 hours

    private string $secret;

    private string $issuer;

    public function __construct()
    {
        $sharedJwtSecret = config('services.webchat.jwt_secret')
            ?: config('services.webchat.fallback_jwt_secret')
            ?: config('app.default_tenant_id');

        $this->secret = $sharedJwtSecret !== null && $sharedJwtSecret !== ''
            ? (string) $sharedJwtSecret
            : (string) config('app.key');
        $this->issuer = config('app.url', 'interazap');
    }

    /**
     * Gerar token signed para uma sessão webchat.
     *
     * Token format: base64url(header).base64url(payload).base64url(signature)
     *
     * @param  string  $sessionId  ID da sessão.
     * @param  string  $tenantId  ID do tenant.
     * @param  string|null  $contactId  ID do contato.
     * @param  string  $ticketId  ID do ticket.
     * @return string Token codificado.
     */
    public function generateToken(
        string $sessionId,
        string $tenantId,
        ?string $contactId,
        string $ticketId,
    ): string {
        $now = time();

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'sub' => $sessionId,
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
            'session_id' => $sessionId,
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'ticket_id' => $ticketId,
            'type' => 'webchat',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac(self::ALGORITHM, $headerEncoded.'.'.$payloadEncoded, $this->secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signatureEncoded;
    }

    /**
     * Validar token e retornar payload.
     *
     * @param  string  $token  Token.
     * @return array<string, mixed>|null Payload decodificado ou null se inválido.
     */
    public function validateToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                Log::warning('[WebChatJwtService] Token invalid format', ['parts' => count($parts)]);

                return null;
            }

            [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

            // Verificar assinatura
            $expectedSignature = hash_hmac(
                self::ALGORITHM,
                $headerEncoded.'.'.$payloadEncoded,
                $this->secret,
                true
            );
            $expectedSignatureEncoded = $this->base64UrlEncode($expectedSignature);

            if (! hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
                Log::warning('[WebChatJwtService] Token signature mismatch');

                return null;
            }

            // Decodificar payload
            $payloadJson = $this->base64UrlDecode($payloadEncoded);
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

            // Verificar expiração
            if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
                Log::debug('[WebChatJwtService] Token expired', ['exp' => $payload['exp']]);

                return null;
            }

            // Validar claims obrigatórios
            if (
                ! isset($payload['session_id'])
                || ! isset($payload['tenant_id'])
                || ! isset($payload['ticket_id'])
            ) {
                Log::warning('[WebChatJwtService] Token missing required claims', [
                    'has_session_id' => isset($payload['session_id']),
                    'has_tenant_id' => isset($payload['tenant_id']),
                    'has_ticket_id' => isset($payload['ticket_id']),
                ]);

                return null;
            }

            // Validar tipo do token
            if (($payload['type'] ?? '') !== 'webchat') {
                Log::warning('[WebChatJwtService] Invalid token type', [
                    'type' => $payload['type'] ?? 'missing',
                ]);

                return null;
            }

            return $payload;
        } catch (\JsonException $e) {
            Log::warning('[WebChatJwtService] Token JSON decode failed', ['error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('[WebChatJwtService] Token validation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Codificar para base64url (URL-safe).
     *
     * @param  string  $data  Dados a codificar.
     * @return string Base64url encoded.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodificar de base64url (URL-safe).
     *
     * @param  string  $data  Dados a decodificar.
     * @return string Decoded string.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
