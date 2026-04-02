<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Connectors;

use Domain\Chat\Contracts\InstanceConnectorPort;
use Domain\Chat\DTOs\ConnectionResultDTO;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Support\Arr;
use RuntimeException;

final class UzapiConnector implements InstanceConnectorPort
{
    public function __construct(
        private readonly UazapiGatewayService $gateway,
        private readonly string $token,
    ) {}

    public function connect(string $mode, ?string $phone = null): ConnectionResultDTO
    {
        $mode = $mode === 'pair' ? 'pair' : 'qr';

        $payload = [
            'mode' => $mode,
            'phone' => $mode === 'pair' ? ($phone ? preg_replace('/\D+/', '', $phone) : null) : null,
        ];

        $response = $this->gateway->connectInstance($this->token, array_filter($payload, fn ($value) => $value !== null));
        $connection = $this->mapGatewayConnection($response, $mode, $phone);

        if ($connection->qrCode === null && $connection->pairCode === null) {
            throw new RuntimeException('Gateway não retornou QR code nem código de pareamento.');
        }

        return $connection;
    }

    public function disconnect(): bool
    {
        $response = $this->gateway->disconnectInstance($this->token);

        return (bool) ($response['success'] ?? true);
    }

    public function restart(): bool
    {
        $success = $this->disconnect();

        return $success;
    }

    public function configureWebhooks(string $webhookUrl, array $events): bool
    {
        $response = $this->gateway->configureWebhook($this->token, [
            'enabled' => true,
            'url' => $webhookUrl,
            'events' => $events,
            'action' => 'add',
        ]);

        return (bool) ($response['success'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function mapGatewayConnection(array $response, string $mode, ?string $phone): ConnectionResultDTO
    {
        $payload = $response['connection'] ?? $response;
        $qr = $this->extractQr($payload);
        $pair = $this->extractPairCode($payload);

        return new ConnectionResultDTO(
            mode: $mode,
            qrCode: $qr,
            pairCode: $pair,
            expiresAt: $this->extractExpiresAt($payload),
            provider: 'uazapi',
            phone: $phone,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractQr(array $response): ?string
    {
        $candidates = [
            $response['instance']['qrcode'] ?? null,
            $response['instance']['qrCode'] ?? null,
            $response['qr_code'] ?? null,
            $response['qrcode'] ?? null,
            $response['qr'] ?? null,
            $response['qrcode_base64'] ?? null,
            $response['qr_base64'] ?? null,
            Arr::get($response, 'instance.qrcode'),
            Arr::get($response, 'data.qrcode'),
        ];

        foreach ($candidates as $qr) {
            if (! $qr) {
                continue;
            }

            if (str_starts_with($qr, 'http')) {
                return $qr;
            }

            if (str_starts_with($qr, 'data:image')) {
                return $qr;
            }

            return 'data:image/png;base64,'.ltrim($qr, 'data:image/png;base64,');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractPairCode(array $response): ?string
    {
        return Arr::get($response, 'instance.paircode')
            ?? Arr::get($response, 'instance.pairCode')
            ?? $response['pair_code']
            ?? $response['pairCode']
            ?? $response['code']
            ?? Arr::get($response, 'data.code')
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractExpiresAt(array $response): \Carbon\Carbon
    {
        $expiresAt = $response['expires_at'] ?? $response['expiration'] ?? null;

        if (is_string($expiresAt)) {
            return \Carbon\Carbon::parse($expiresAt);
        }

        return now()->addMinutes(5);
    }
}
