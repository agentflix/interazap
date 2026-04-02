<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Adapters;

use Carbon\Carbon;
use Domain\Chat\Contracts\WhatsAppProviderPort;
use Domain\Chat\DTOs\ProviderMessageDTO;
use Domain\Chat\DTOs\ProviderStatusDTO;
use Domain\Chat\DTOs\SendMediaPayloadDTO;
use Domain\Chat\DTOs\SendTextPayloadDTO;
use Domain\Chat\Enums\MessageDeliveryStatus;
use Domain\Shared\Infrastructure\WhatsApp\Concerns\HasCircuitBreaker;
use Domain\Shared\Infrastructure\WhatsApp\Concerns\HasObservability;
use Domain\Shared\Infrastructure\WhatsApp\Concerns\HasRetryPolicy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

final class UzapiAdapter implements WhatsAppProviderPort
{
    use HasCircuitBreaker;
    use HasObservability;
    use HasRetryPolicy;

    private string $baseUrl;

    public function __construct(
        private readonly string $token,
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getProviderName(): string
    {
        return 'uazapi';
    }

    public function sendText(SendTextPayloadDTO $payload): ProviderMessageDTO
    {
        $this->generateRequestId();
        $startTime = $this->logOperationStart('sendText');

        try {
            $response = $this->withCircuitBreaker('uazapi.sendText', function () use ($payload): array {
                return $this->post('/send/text', [
                    'number' => $payload->phone,
                    'text' => $payload->message,
                    'delay' => $payload->delayMs,
                    ...($payload->quotedMessageId ? ['replyid' => $payload->quotedMessageId] : []),
                ]);
            });

            $dto = $this->mapMessageResponse($response, MessageDeliveryStatus::fromUzapi((string) ($response['status'] ?? 'sent')));
            $this->logOperationEnd('sendText', $startTime, $dto->isSuccess());

            return $dto;
        } catch (\Throwable $e) {
            $this->logOperationEnd('sendText', $startTime, false, [
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ]);

            return ProviderMessageDTO::failed('EXCEPTION', $e->getMessage());
        }
    }

    public function sendMedia(SendMediaPayloadDTO $payload): ProviderMessageDTO
    {
        $this->generateRequestId();
        $startTime = $this->logOperationStart('sendMedia');

        try {
            $response = $this->withCircuitBreaker('uazapi.sendMedia', function () use ($payload): array {
                return $this->post('/send/file', [
                    'number' => $payload->phone,
                    'type' => $payload->type->value,
                    'file' => $payload->media,
                    'text' => $payload->caption ?? '',
                    'fileName' => $payload->fileName,
                    'extension' => $payload->extension,
                    'isBase64' => $payload->isBase64,
                    ...($payload->quotedMessageId ? ['replyid' => $payload->quotedMessageId] : []),
                ]);
            });

            $dto = $this->mapMessageResponse($response, MessageDeliveryStatus::fromUzapi((string) ($response['status'] ?? 'sent')));
            $this->logOperationEnd('sendMedia', $startTime, $dto->isSuccess());

            return $dto;
        } catch (\Throwable $e) {
            $this->logOperationEnd('sendMedia', $startTime, false, [
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ]);

            return ProviderMessageDTO::failed('EXCEPTION', $e->getMessage());
        }
    }

    public function markAsRead(string $chatId): bool
    {
        $this->generateRequestId();
        $startTime = $this->logOperationStart('markAsRead');

        try {
            $this->withCircuitBreaker('uazapi.markAsRead', function () use ($chatId): array {
                return $this->post('/chat/read', [
                    'number' => $chatId,
                    'read' => true,
                ]);
            });

            $this->logOperationEnd('markAsRead', $startTime, true);

            return true;
        } catch (\Throwable $e) {
            $this->logOperationEnd('markAsRead', $startTime, false, [
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendPresence(string $chatId, string $presence): bool
    {
        $this->generateRequestId();
        $startTime = $this->logOperationStart('sendPresence');

        try {
            $this->withCircuitBreaker('uazapi.sendPresence', function () use ($chatId, $presence): array {
                return $this->post('/message/presence', [
                    'number' => $chatId,
                    'presence' => $presence,
                ]);
            });

            $this->logOperationEnd('sendPresence', $startTime, true);

            return true;
        } catch (\Throwable $e) {
            $this->logOperationEnd('sendPresence', $startTime, false, [
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getInstanceStatus(): ProviderStatusDTO
    {
        $this->generateRequestId();
        $startTime = $this->logOperationStart('getInstanceStatus');

        try {
            $response = $this->withCircuitBreaker('uazapi.getInstanceStatus', function (): array {
                return $this->get('/instance/status');
            });

            $status = (string) ($response['status'] ?? '');
            $connected = (bool) ($response['connected'] ?? Str::upper($status) === 'CONNECTED');

            $dto = new ProviderStatusDTO(
                connected: $connected,
                phone: $response['phone'] ?? null,
                name: $response['profileName'] ?? $response['name'] ?? null,
                rawResponse: $response,
            );

            $this->logOperationEnd('getInstanceStatus', $startTime, true);

            return $dto;
        } catch (\Throwable $e) {
            $this->logOperationEnd('getInstanceStatus', $startTime, false, [
                'error_message' => $e->getMessage(),
            ]);

            return new ProviderStatusDTO(connected: false, rawResponse: ['error' => $e->getMessage()]);
        }
    }

    public function checkNumberExists(string $phone): bool
    {
        $this->generateRequestId();
        $startTime = $this->logOperationStart('checkNumberExists');

        try {
            $response = $this->withCircuitBreaker('uazapi.checkNumberExists', function () use ($phone): array {
                return $this->post('/check-number', ['number' => $phone]);
            });

            $exists = (bool) ($response['exists'] ?? $response['is_whatsapp'] ?? $response['valid'] ?? false);
            $this->logOperationEnd('checkNumberExists', $startTime, true);

            return $exists;
        } catch (\Throwable $e) {
            $this->logOperationEnd('checkNumberExists', $startTime, false, [
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getProfilePicture(string $phone): ?string
    {
        $this->generateRequestId();
        $startTime = $this->logOperationStart('getProfilePicture');

        try {
            $response = $this->withCircuitBreaker('uazapi.getProfilePicture', function () use ($phone): array {
                return $this->post('/profile/picture', ['number' => $phone]);
            });

            $url = $response['url'] ?? $response['profilePicUrl'] ?? $response['picture'] ?? null;
            $this->logOperationEnd('getProfilePicture', $startTime, true);

            return is_string($url) ? $url : null;
        } catch (\Throwable $e) {
            $this->logOperationEnd('getProfilePicture', $startTime, false, [
                'error_message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function mapMessageResponse(array $response, MessageDeliveryStatus $status): ProviderMessageDTO
    {
        if (($response['success'] ?? true) === false || isset($response['error'])) {
            $error = $response['error'] ?? [];

            return ProviderMessageDTO::failed(
                (string) ($error['code'] ?? 'PROVIDER_ERROR'),
                (string) ($error['message'] ?? $response['message'] ?? 'Provider error'),
                $response,
            );
        }

        $providerMessageId = (string) ($response['message_id']
            ?? $response['messageId']
            ?? $response['id']
            ?? $response['messageid']
            ?? '');

        $timestamp = $response['timestamp'] ?? $response['time'] ?? null;
        $sentAt = $timestamp ? Carbon::createFromTimestampMs((int) $timestamp) : Carbon::now();

        return new ProviderMessageDTO(
            providerMessageId: $providerMessageId,
            status: $status,
            sentAt: $sentAt,
            rawResponse: $response,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->client()->post($this->baseUrl.$path, $payload)->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        $response = $this->client()->get($this->baseUrl.$path)->throw();

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return $this->createHttpClient()->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ]);
    }
}
