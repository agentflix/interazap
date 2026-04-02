<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para Mensagens de Ticket.
 *
 * Centraliza os dados necessários para o registro de novas mensagens de chat,
 * suportando mensagens de texto, mídias (arquivos, áudios) e integração com gateways.
 *
 * @category DTOs
 */
final readonly class ChatMessageDTO
{
    public const SOURCE_WEBHOOK = 'webhook';

    public const SOURCE_AGENT = 'agent';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_BOT = 'bot';

    /**
     * @param  string  $ticketId  UUID do ticket vinculado.
     * @param  string  $content  Conteúdo textual ou descrição da mensagem.
     * @param  string  $direction  Direção ('incoming' para recebido, 'outgoing' para enviado).
     * @param  string  $type  Tipo da mensagem (text, file, audio, etc).
     * @param  bool  $isFromContact  Indica se a mensagem foi originada pelo cliente.
     * @param  string|null  $externalId  ID único da mensagem no provedor externo.
     * @param  string|null  $contactId  UUID do contato vinculado.
     * @param  string|null  $userId  UUID do usuário (agente) que enviou a mensagem.
     * @param  array<string, mixed>|null  $metadata  Metadados brutos ou adicionais do evento.
     * @param  string|null  $fileUrl  URL pública para acesso ao arquivo de mídia.
     * @param  string|null  $fileName  Nome original do arquivo.
     * @param  string|null  $mimeType  Tipo MIME da mídia.
     * @param  int|null  $fileSize  Tamanho do arquivo em bytes.
     * @param  string  $source  Origem da mensagem (webhook, agent, system, bot).
     */
    public function __construct(
        public string $ticketId,
        public string $content,
        public string $direction = 'incoming',
        public string $type = 'text',
        public bool $isFromContact = true,
        public ?string $externalId = null,
        public ?string $contactId = null,
        public ?string $userId = null,
        public ?array $metadata = null,
        public ?string $fileUrl = null,
        public ?string $fileName = null,
        public ?string $mimeType = null,
        public ?int $fileSize = null,
        public string $source = self::SOURCE_WEBHOOK,
    ) {}

    /**
     * Cria uma instância do DTO a partir de um Request HTTP.
     *
     * @param  Request  $request  Objeto da requisição.
     * @param  string  $ticketId  UUID do ticket (geralmente vindo da rota).
     * @return self Instância populada.
     */
    public static function fromRequest(Request $request, string $ticketId): self
    {
        return new self(
            ticketId: $ticketId,
            content: $request->string('content')->toString(),
            direction: (string) $request->input('direction', 'incoming'),
            type: (string) $request->input('type', 'text'),
            isFromContact: (bool) $request->input('is_from_contact', true),
            externalId: $request->input('external_id'),
            contactId: $request->input('contact_id'),
            userId: $request->input('user_id'),
            metadata: $request->input('metadata') ? (array) $request->input('metadata') : null,
            fileUrl: $request->input('file_url'),
            fileName: $request->input('file_name'),
            mimeType: $request->input('mime_type'),
            fileSize: $request->input('file_size') ? (int) $request->input('file_size') : null,
            source: (string) $request->input('source', self::SOURCE_AGENT),
        );
    }

    /**
     * Cria uma instância do DTO a partir de um array associativo.
     *
     * @param  array<string, mixed>  $data  Dados da mensagem.
     * @return self Instância populada.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ticketId: $data['ticket_id'],
            content: $data['content'] ?? '',
            direction: $data['direction'] ?? 'incoming',
            type: $data['type'] ?? 'text',
            isFromContact: (bool) ($data['is_from_contact'] ?? true),
            externalId: $data['external_id'] ?? null,
            contactId: $data['contact_id'] ?? null,
            userId: $data['user_id'] ?? null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : null,
            fileUrl: $data['file_url'] ?? null,
            fileName: $data['file_name'] ?? null,
            mimeType: $data['mime_type'] ?? null,
            fileSize: isset($data['file_size']) ? (int) $data['file_size'] : null,
            source: $data['source'] ?? self::SOURCE_WEBHOOK,
        );
    }

    /**
     * Converte o DTO em um array associativo para persistência ou resposta.
     *
     * @return array<string, mixed> Dados formatados em array.
     */
    public function toArray(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'content' => $this->content,
            'direction' => $this->direction,
            'type' => $this->type,
            'is_from_contact' => $this->isFromContact,
            'external_id' => $this->externalId,
            'contact_id' => $this->contactId,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
            'file_url' => $this->fileUrl,
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'source' => $this->source,
        ];
    }
}
