<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

use Domain\Chat\DTOs\ProviderMessageDTO;
use Domain\Chat\DTOs\ProviderStatusDTO;
use Domain\Chat\DTOs\SendMediaPayloadDTO;
use Domain\Chat\DTOs\SendTextPayloadDTO;

/**
 * Port de comunicação com provedores WhatsApp.
 */
interface WhatsAppProviderPort
{
    public function getProviderName(): string;

    public function sendText(SendTextPayloadDTO $payload): ProviderMessageDTO;

    public function sendMedia(SendMediaPayloadDTO $payload): ProviderMessageDTO;

    public function markAsRead(string $chatId): bool;

    public function sendPresence(string $chatId, string $presence): bool;

    public function getInstanceStatus(): ProviderStatusDTO;

    public function checkNumberExists(string $phone): bool;

    public function getProfilePicture(string $phone): ?string;
}
