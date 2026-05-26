<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

use Domain\Chat\DTOs\ProviderMessageDTO;
use Domain\Chat\DTOs\ProviderStatusDTO;
use Domain\Chat\DTOs\SendMediaPayloadDTO;
use Domain\Chat\DTOs\SendTextPayloadDTO;

/**
 * Port de comunicação com provedores WhatsApp.
 *
 * Abstrai as operações de envio de mensagens, consulta de status e
 * verificação de números, desacoplando o domínio do SDK de cada provedor.
 */
interface WhatsAppProviderPort
{
    /** Retorna o nome do provedor (ex.: "uazapi", "zapi", "meta"). */
    public function getProviderName(): string;

    /** Envia uma mensagem de texto simples via provedor. */
    public function sendText(SendTextPayloadDTO $payload): ProviderMessageDTO;

    /** Envia uma mensagem com arquivo de mídia (imagem, áudio, documento, etc.). */
    public function sendMedia(SendMediaPayloadDTO $payload): ProviderMessageDTO;

    /** Marca a conversa como lida no provedor. */
    public function markAsRead(string $chatId): bool;

    /** Envia indicador de presença (digitando, gravando, etc.) para um chat. */
    public function sendPresence(string $chatId, string $presence): bool;

    /** Obtém o status de conexão atual da instância no provedor. */
    public function getInstanceStatus(): ProviderStatusDTO;

    /** Verifica se o número de telefone informado possui conta WhatsApp ativa. */
    public function checkNumberExists(string $phone): bool;

    /** Retorna a URL da foto de perfil do número, ou null se não disponível. */
    public function getProfilePicture(string $phone): ?string;
}
