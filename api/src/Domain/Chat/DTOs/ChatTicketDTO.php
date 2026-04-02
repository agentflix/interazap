<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Chat Ticket.
 *
 * @readonly
 */
final readonly class ChatTicketDTO
{
    /**
     * @param  string  $channel  Channel (e.g. whatsapp).
     * @param  string|null  $contactId  Contact UUID.
     * @param  string|null  $instanceId  Connection instance UUID.
     * @param  string|null  $remoteJid  Remote unique identifier on channel.
     * @param  string|null  $subject  Ticket subject/title.
     * @param  string|null  $priority  Priority (low, normal, high).
     * @param  string|null  $pushName  Display name from channel.
     * @param  string|null  $profilePictureUrl  Contact profile picture URL.
     * @param  string|null  $phone  Formatted phone number.
     * @param  string|null  $phoneE164  E.164 international format phone number.
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
     * Create DTO from request.
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
     * Create DTO from array.
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
