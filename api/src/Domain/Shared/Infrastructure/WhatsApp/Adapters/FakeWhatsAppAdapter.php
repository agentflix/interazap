<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Adapters;

use Carbon\Carbon;
use Domain\Chat\Contracts\WhatsAppProviderPort;
use Domain\Chat\DTOs\ProviderMessageDTO;
use Domain\Chat\DTOs\ProviderStatusDTO;
use Domain\Chat\DTOs\SendMediaPayloadDTO;
use Domain\Chat\DTOs\SendTextPayloadDTO;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fake adapter para testes.
 */
final class FakeWhatsAppAdapter implements WhatsAppProviderPort
{
    /** @var array<string, ProviderMessageDTO> */
    private array $sentMessages = [];

    /** @var list<array{phone:string,message:string}> */
    private array $sentPayloads = [];

    private bool $shouldFail = false;

    private ?string $failureCode = null;

    public function getProviderName(): string
    {
        return 'fake';
    }

    public function sendText(SendTextPayloadDTO $payload): ProviderMessageDTO
    {
        if ($this->shouldFail) {
            $this->shouldFail = false;

            return ProviderMessageDTO::failed(
                $this->failureCode ?? 'FAKE_ERROR',
                'Simulated failure',
            );
        }

        $dto = ProviderMessageDTO::success(
            providerMessageId: 'fake_'.Str::random(20),
            sentAt: Carbon::now(),
        );

        $this->sentMessages[$dto->providerMessageId] = $dto;
        $this->sentPayloads[] = [
            'phone' => $payload->phone,
            'message' => $payload->message,
        ];

        return $dto;
    }

    public function sendMedia(SendMediaPayloadDTO $payload): ProviderMessageDTO
    {
        return $this->sendText(new SendTextPayloadDTO(
            phone: $payload->phone,
            message: $payload->caption ?? '[Media]',
        ));
    }

    public function markAsRead(string $chatId): bool
    {
        return true;
    }

    public function sendPresence(string $chatId, string $presence): bool
    {
        return true;
    }

    public function getInstanceStatus(): ProviderStatusDTO
    {
        return new ProviderStatusDTO(
            connected: true,
            phone: '5511999999999',
            name: 'Fake Instance',
        );
    }

    public function checkNumberExists(string $phone): bool
    {
        return true;
    }

    public function getProfilePicture(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }

        return 'https://example.com/fake-profile.jpg';
    }

    public function shouldFailNextWith(string $errorCode): void
    {
        $this->shouldFail = true;
        $this->failureCode = $errorCode;
    }

    /**
     * @return array<string, ProviderMessageDTO>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
        $this->sentPayloads = [];
        $this->shouldFail = false;
        $this->failureCode = null;
    }

    public function assertSentTo(string $phone): void
    {
        foreach ($this->sentPayloads as $payload) {
            if ($payload['phone'] === $phone) {
                return;
            }
        }

        throw new RuntimeException("No messages sent to {$phone}.");
    }
}
