<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO de Ticket de Chat.
 *
 * @readonly
 */
final readonly class ChatTicketDTO
{
    /**
     * @param  string  $channel  Canal de atendimento (ex.: whatsapp).
     * @param  string|null  $contactId  UUID do contato.
     * @param  string|null  $instanceId  UUID da instância de conexão.
     * @param  string|null  $remoteJid  Identificador remoto único no canal.
     * @param  string|null  $subject  Assunto ou título do ticket.
     * @param  string|null  $priority  Prioridade (low, normal, high).
     * @param  string|null  $pushName  Nome de exibição fornecido pelo canal.
     * @param  string|null  $profilePictureUrl  URL da foto de perfil do contato.
     * @param  string|null  $phone  Número de telefone formatado.
     * @param  string|null  $phoneE164  Número de telefone no formato E.164.
     */
    public function __construct(
        public string $channel,
        public ?string $contactId = null,
        public ?string $instanceId = null,
        public ?string $remoteJid = null,
        public ?string $subject = null,
        public ?string $priority = 'normal',
        public ?string $pushName = null,
        public ?string $profilePictureUrl = null,
        public ?string $phone = null,
        public ?string $phoneE164 = null,
    ) {}

    /**
     * Cria o DTO a partir de um Request HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            channel: (string) $request->input('channel', 'whatsapp'),
            contactId: $request->input('contact_id'),
            instanceId: $request->input('instance_id'),
            remoteJid: $request->input('remote_jid'),
            subject: $request->input('subject'),
            priority: (string) $request->input('priority', 'normal'),
            pushName: $request->input('push_name'),
            profilePictureUrl: $request->input('profile_picture_url'),
            phone: $request->input('phone'),
            phoneE164: $request->input('phone_e164'),
        );
    }

    /**
     * Cria o DTO a partir de um array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: $data['channel'] ?? 'whatsapp',
            contactId: $data['contact_id'] ?? null,
            instanceId: $data['instance_id'] ?? null,
            remoteJid: $data['remote_jid'] ?? null,
            subject: $data['subject'] ?? null,
            priority: $data['priority'] ?? 'normal',
            pushName: $data['push_name'] ?? null,
            profilePictureUrl: $data['profile_picture_url'] ?? null,
            phone: $data['phone'] ?? null,
            phoneE164: $data['phone_e164'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'contact_id' => $this->contactId,
            'instance_id' => $this->instanceId,
            'remote_jid' => $this->remoteJid,
            'subject' => $this->subject,
            'priority' => $this->priority,
            'push_name' => $this->pushName,
            'profile_picture_url' => $this->profilePictureUrl,
            'phone' => $this->phone,
            'phone_e164' => $this->phoneE164,
        ];
    }
}
