<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

/**
 * Contrato para serviços de gateway WhatsApp.
 *
 * Define a interface de envio de mensagens via provedores da API WhatsApp.
 */
interface ChatWhatsAppGatewayInterface
{
    /**
     * Envia uma mensagem via WhatsApp.
     *
     * @param  string  $instanceId  Identificador da instância WhatsApp.
     * @param  string  $to  Número de telefone do destinatário.
     * @param  string  $content  Conteúdo da mensagem.
     * @param  string  $type  Tipo da mensagem (text, image, document, etc.).
     * @param  array<string, mixed>  $metadata  Metadados adicionais.
     * @return array<string, mixed> Resposta retornada pelo provedor.
     *
     * @throws \RuntimeException Se a mensagem não puder ser enviada.
     */
    public function sendMessage(
        string $instanceId,
        string $to,
        string $content,
        string $type = 'text',
        array $metadata = [],
    ): array;

    /**
     * Obtém o status de uma mensagem enviada.
     *
     * @param  string  $instanceId  Identificador da instância WhatsApp.
     * @param  string  $messageId  ID da mensagem no provedor.
     * @return array<string, mixed> Status da mensagem.
     */
    public function getMessageStatus(string $instanceId, string $messageId): array;

    /**
     * Verifica se a instância está conectada e pronta para uso.
     *
     * @param  string  $instanceId  Identificador da instância WhatsApp.
     */
    public function isConnected(string $instanceId): bool;
}
