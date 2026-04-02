<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Normalizers;

use Carbon\Carbon;
use Domain\Chat\Contracts\WebhookNormalizerPort;
use Domain\Chat\DTOs\WebhookEventDTO;
use Domain\Chat\Enums\WebhookEventType;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UzapiWebhookNormalizer implements WebhookNormalizerPort
{
    public function getProviderName(): string
    {
        return 'uazapi';
    }

    public function normalize(array $rawPayload): WebhookEventDTO
    {
        if (! $this->supports($rawPayload)) {
            throw new InvalidArgumentException('Payload não suportado pelo normalizer Uzapi.');
        }

        $eventTypeRaw = $this->resolveEventTypeRaw($rawPayload);
        $eventType = $this->mapEventType($eventTypeRaw, $rawPayload);

        return match ($eventType) {
            WebhookEventType::MESSAGE => $this->normalizeMessage($rawPayload),
            WebhookEventType::MESSAGE_STATUS => $this->normalizeMessageStatus($rawPayload),
            WebhookEventType::CONNECTION => $this->normalizeConnection($rawPayload),
            WebhookEventType::PRESENCE => $this->normalizePresence($rawPayload),
        };
    }

    public function supports(array $rawPayload): bool
    {
        $eventType = $this->resolveEventTypeRaw($rawPayload);

        if ($eventType !== '') {
            return true;
        }

        if (isset($rawPayload['message']) && is_array($rawPayload['message'])) {
            return true;
        }

        if (isset($rawPayload['event']) && is_array($rawPayload['event'])) {
            return true;
        }

        if (isset($rawPayload['instance']) && is_array($rawPayload['instance'])) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function normalizeMessage(array $rawPayload): WebhookEventDTO
    {
        $message = (array) ($rawPayload['message'] ?? []);
        $chat = (array) ($rawPayload['chat'] ?? []);

        $providerMessageId = $this->firstString([
            $this->stringOrNull($message['messageid'] ?? null),
            $this->stringOrNull($message['messageId'] ?? null),
            $this->stringOrNull($message['message_id'] ?? null),
            $this->stringOrNull($message['id'] ?? null),
        ]) ?? '';

        $chatId = $this->firstString([
            $this->stringOrNull($message['chatid'] ?? null),
            $this->stringOrNull($message['from'] ?? null),
            $this->stringOrNull($message['sender'] ?? null),
            $this->stringOrNull(Arr::get($chat, 'wa_chatid')),
        ]) ?? '';

        $phone = $this->normalizePhone($chatId);
        if ($phone === '') {
            $phone = $this->normalizePhone($this->firstString([
                $this->stringOrNull(Arr::get($chat, 'phone')),
                $this->stringOrNull($rawPayload['owner'] ?? null),
                $this->stringOrNull(Arr::get($chat, 'owner')),
            ]) ?? '');
        }

        $direction = (bool) ($message['fromMe'] ?? false) ? 'outgoing' : 'incoming';

        $textContent = $this->firstString([
            $this->stringOrNull($message['text'] ?? null),
            $this->stringOrNull($message['body'] ?? null),
            $this->stringOrNull(Arr::get($message, 'content.text')),
            $this->stringOrNull(Arr::get($message, 'content.caption')),
        ]);

        $mediaType = $this->normalizeMediaType($this->firstString([
            $this->stringOrNull($message['mediaType'] ?? null),
            $this->stringOrNull($message['type'] ?? null),
            $this->stringOrNull($message['messageType'] ?? null),
            $this->stringOrNull(Arr::get($message, 'content.type')),
        ]));

        $mediaUrl = $this->firstString([
            $this->stringOrNull(Arr::get($message, 'content.URL')),
            $this->stringOrNull(Arr::get($message, 'content.url')),
            $this->stringOrNull(Arr::get($message, 'content.mediaUrl')),
            $this->stringOrNull(Arr::get($message, 'content.media_url')),
            $this->stringOrNull(Arr::get($message, 'content.file')),
        ]);

        $senderName = $this->firstString([
            $this->stringOrNull($message['senderName'] ?? null),
            $this->stringOrNull(Arr::get($chat, 'wa_name')),
            $this->stringOrNull(Arr::get($chat, 'name')),
        ]);

        $quotedMessageId = $this->firstString([
            $this->stringOrNull($message['quoted'] ?? null),
            $this->stringOrNull($message['quotedMessageId'] ?? null),
            $this->stringOrNull($message['quotedMessageID'] ?? null),
        ]);

        $timestamp = $this->parseTimestamp(
            $message['messageTimestamp']
                ?? $message['timestamp']
                ?? Arr::get($message, 'content.senderTimestampMS')
                ?? Arr::get($message, 'content.timestamp')
                ?? null,
        );

        $isGroup = (bool) ($message['isGroup'] ?? Arr::get($chat, 'wa_isGroup', false));
        if (! $isGroup && $this->isGroupJid($chatId)) {
            $isGroup = true;
        }

        return new WebhookEventDTO(
            eventType: WebhookEventType::MESSAGE,
            providerMessageId: $providerMessageId,
            phone: $phone,
            senderName: $senderName,
            direction: $direction,
            textContent: $textContent,
            mediaType: $mediaType,
            mediaUrl: $mediaUrl,
            timestamp: $timestamp,
            isGroup: $isGroup,
            status: null,
            quotedMessageId: $quotedMessageId,
            rawPayload: $rawPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function normalizeMessageStatus(array $rawPayload): WebhookEventDTO
    {
        $event = (array) ($rawPayload['event'] ?? []);

        $providerMessageId = $this->firstString([
            $this->stringOrNull(Arr::get($event, 'MessageIDs.0')),
            $this->stringOrNull($event['messageId'] ?? null),
            $this->stringOrNull($event['id'] ?? null),
        ]) ?? '';

        $chatId = $this->firstString([
            $this->stringOrNull($event['Chat'] ?? null),
            $this->stringOrNull($event['Sender'] ?? null),
        ]) ?? '';

        $phone = $this->normalizePhone($chatId);
        if ($phone === '') {
            $phone = $this->normalizePhone($this->firstString([
                $this->stringOrNull($rawPayload['owner'] ?? null),
            ]) ?? '');
        }

        $direction = (bool) ($event['IsFromMe'] ?? false) ? 'outgoing' : 'incoming';
        $status = $this->normalizeStatus($this->firstString([
            $this->stringOrNull($event['Type'] ?? null),
            $this->stringOrNull($rawPayload['state'] ?? null),
            $this->stringOrNull($rawPayload['type'] ?? null),
        ]));

        $timestamp = $this->parseTimestamp($event['Timestamp'] ?? $event['timestamp'] ?? null);
        $isGroup = (bool) ($event['IsGroup'] ?? false);
        if (! $isGroup && $this->isGroupJid($chatId)) {
            $isGroup = true;
        }

        return new WebhookEventDTO(
            eventType: WebhookEventType::MESSAGE_STATUS,
            providerMessageId: $providerMessageId,
            phone: $phone,
            senderName: null,
            direction: $direction,
            textContent: null,
            mediaType: null,
            mediaUrl: null,
            timestamp: $timestamp,
            isGroup: $isGroup,
            status: $status,
            quotedMessageId: null,
            rawPayload: $rawPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function normalizeConnection(array $rawPayload): WebhookEventDTO
    {
        $instance = (array) ($rawPayload['instance'] ?? []);
        $phone = $this->normalizePhone($this->firstString([
            $this->stringOrNull($rawPayload['owner'] ?? null),
            $this->stringOrNull(Arr::get($instance, 'owner')),
            $this->stringOrNull(Arr::get($instance, 'name')),
        ]) ?? '');

        $status = $this->normalizeStatus($this->firstString([
            $this->stringOrNull(Arr::get($instance, 'status')),
            $this->stringOrNull($rawPayload['state'] ?? null),
            $this->stringOrNull($rawPayload['type'] ?? null),
        ]));

        $timestamp = $this->parseTimestamp($instance['lastDisconnect'] ?? $instance['lastDisconnectAt'] ?? null);

        return new WebhookEventDTO(
            eventType: WebhookEventType::CONNECTION,
            providerMessageId: '',
            phone: $phone,
            senderName: null,
            direction: 'system',
            textContent: null,
            mediaType: null,
            mediaUrl: null,
            timestamp: $timestamp,
            isGroup: false,
            status: $status,
            quotedMessageId: null,
            rawPayload: $rawPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function normalizePresence(array $rawPayload): WebhookEventDTO
    {
        $presence = (array) ($rawPayload['presence'] ?? []);
        $jid = $this->firstString([
            $this->stringOrNull($presence['jid'] ?? null),
            $this->stringOrNull($rawPayload['owner'] ?? null),
        ]) ?? '';

        $phone = $this->normalizePhone($jid);
        $status = $this->normalizeStatus($this->firstString([
            $this->stringOrNull($presence['type'] ?? null),
            $this->stringOrNull($rawPayload['state'] ?? null),
        ]));

        $timestamp = $this->parseTimestamp($presence['timestamp'] ?? null);

        return new WebhookEventDTO(
            eventType: WebhookEventType::PRESENCE,
            providerMessageId: '',
            phone: $phone,
            senderName: null,
            direction: 'system',
            textContent: null,
            mediaType: null,
            mediaUrl: null,
            timestamp: $timestamp,
            isGroup: $this->isGroupJid($jid),
            status: $status,
            quotedMessageId: null,
            rawPayload: $rawPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function resolveEventTypeRaw(array $rawPayload): string
    {
        $eventType = $rawPayload['EventType'] ?? $rawPayload['event_type'] ?? $rawPayload['event'] ?? null;

        if (is_string($eventType)) {
            return strtolower($eventType);
        }

        if (isset($rawPayload['message']) && is_array($rawPayload['message'])) {
            return 'messages';
        }

        if (isset($rawPayload['event']) && is_array($rawPayload['event'])) {
            return 'messages_update';
        }

        if (isset($rawPayload['instance']) && is_array($rawPayload['instance'])) {
            return 'connection';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function mapEventType(string $eventTypeRaw, array $rawPayload): WebhookEventType
    {
        return match (true) {
            str_starts_with($eventTypeRaw, 'messages_update'),
            str_contains($eventTypeRaw, 'messages_update'),
            str_contains($eventTypeRaw, 'message_update'),
            str_contains($eventTypeRaw, 'message_status'),
            str_contains($eventTypeRaw, 'readreceipt') => WebhookEventType::MESSAGE_STATUS,
            str_contains($eventTypeRaw, 'connection') => WebhookEventType::CONNECTION,
            str_contains($eventTypeRaw, 'presence') => WebhookEventType::PRESENCE,
            default => WebhookEventType::MESSAGE,
        };
    }

    private function normalizePhone(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', '', $value) ?? $value;
        $value = str_replace(['@s.whatsapp.net', '@g.us'], '', $value);

        return $value;
    }

    private function isGroupJid(string $value): bool
    {
        return str_contains($value, '@g.us');
    }

    private function normalizeMediaType(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = Str::lower($value);

        return match ($normalized) {
            'extendedtextmessage', 'conversation' => 'text',
            'imagemessage' => 'image',
            'videomessage' => 'video',
            'audiomessage' => 'audio',
            'documentmessage' => 'document',
            default => $normalized,
        };
    }

    private function normalizeStatus(?string $value): ?string
    {
        return $value ? Str::lower($value) : null;
    }

    private function parseTimestamp(mixed $value): Carbon
    {
        if (is_numeric($value)) {
            $numeric = (int) $value;

            return $numeric > 1_000_000_000_000
                ? Carbon::createFromTimestampMs($numeric)
                : Carbon::createFromTimestamp($numeric);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return Carbon::now();
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::of($value)->trim()->isNotEmpty()
            ? Str::of($value)->trim()->value()
            : null;
    }
}
