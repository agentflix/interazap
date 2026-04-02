<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dispatches outbound chat messages through configured gateway providers.
 */
final class ChatMessageGatewayDispatcher
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
        private readonly ChatMessageRealtimePublisher $realtimePublisher,
    ) {}

    public function dispatch(ChatMessage $message, ChatTicket $ticket): void
    {
        $message->loadMissing('extended');
        $instance = $ticket->instance;
        $provider = $instance instanceof ChatInstance ? $instance->provider : 'uazapi';
        $token = $instance instanceof ChatInstance ? $this->resolveInstanceToken($instance) : null;
        $instanceId = $instance instanceof ChatInstance ? (string) $instance->id : null;

        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;
        if (! $number && $ticket->contact) {
            $number = $ticket->contact->whatsapp ?? $ticket->contact->phone;
        }

        if (! $token || ! $number || ($provider === 'zapi' && ! $instanceId)) {
            $message->status = 'failed';
            $message->error_message = 'Instance token or destination missing';
            $message->save();
            $this->realtimePublisher->emitStatus($message);

            return;
        }

        $number = str_contains((string) $number, '@') ? Str::before($number, '@') : $number;
        $cleaned = preg_replace('/[^0-9]/', '', (string) $number);
        $number = is_string($cleaned) ? $cleaned : '';

        if ($number === '') {
            $message->status = 'failed';
            $message->error_message = 'Número destino inválido';
            $message->save();
            $this->realtimePublisher->emitStatus($message);

            return;
        }

        $replyId = null;
        if (isset($message->metadata['quoted_message_id'])) {
            $quoted = ChatMessage::query()
                ->select(['id', 'external_id'])
                ->where('tenant_id', (string) $message->tenant_id)
                ->where('ticket_id', (string) $message->ticket_id)
                ->where('id', (string) $message->metadata['quoted_message_id'])
                ->first();

            if ($quoted && $quoted->external_id) {
                $replyId = $quoted->external_id;
            }
        }

        try {
            $isMedia = in_array($message->type, ['file', 'video', 'image', 'sticker', 'document'], true)
                || $message->mime_type
                || $message->file_url;

            if ($provider === 'zapi') {
                if ($isMedia || $message->type === 'audio') {
                    $filePayload = $this->resolveFilePayload($message);
                    $mediaType = $message->type === 'audio' ? 'audio' : $this->resolveMediaType($message);

                    $response = $this->gateway->sendOutboundMessage('zapi', $token, $ticket->tenant_id, $instanceId, [
                        'type' => 'media',
                        'to' => $number,
                        'mediaType' => $mediaType,
                        'mediaUrl' => $filePayload['file'] ?? $message->file_url,
                        'caption' => $message->type === 'audio' ? $message->content : $this->fallbackCaption($message),
                        'fileName' => $message->file_name,
                    ]);
                } else {
                    $response = $this->gateway->sendOutboundMessage('zapi', $token, $ticket->tenant_id, $instanceId, [
                        'type' => 'text',
                        'to' => $number,
                        'text' => $message->type === 'location'
                            ? $this->buildLocationMessage($message)
                            : $this->buildOutboundText($message, $instance),
                    ]);
                }
            } elseif ($isMedia) {
                $filePayload = $this->resolveFilePayload($message);
                $response = $this->gateway->sendFile($token, [
                    'number' => $number,
                    ...$filePayload,
                    'caption' => $this->fallbackCaption($message),
                    'type' => $this->resolveMediaType($message),
                    ...($message->file_name ? ['fileName' => $message->file_name] : []),
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            } elseif ($message->type === 'location') {
                $response = $this->gateway->sendText($token, [
                    'number' => $number,
                    'text' => $this->buildLocationMessage($message),
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            } elseif ($message->type === 'audio') {
                $filePayload = $this->resolveFilePayload($message);
                $response = $this->gateway->sendAudio($token, [
                    'number' => $number,
                    ...$filePayload,
                    'caption' => $message->content,
                    'type' => 'ptt',
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            } elseif ($message->type === 'template') {
                $response = $this->gateway->sendTemplate($token, [
                    'number' => $number,
                    'templateId' => $message->metadata['template']['name'] ?? '',
                    'language' => $message->metadata['template']['language']['code'] ?? 'pt_BR',
                    'components' => $message->metadata['template']['components'] ?? [],
                ]);
            } else {
                $response = $this->gateway->sendText($token, [
                    'number' => $number,
                    'text' => $this->buildOutboundText($message, $instance),
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            }

            $message->status = 'sent';
            $message->sent_at = now();
            $message->external_id = $response['messageid'] ?? $response['id'] ?? $message->external_id;
            $message->metadata = array_merge($message->metadata ?? [], ['gateway_response' => $response]);
            $message->save();
            $this->realtimePublisher->emitStatus($message);
        } catch (\Throwable $exception) {
            $message->status = 'failed';
            $message->error_message = $exception->getMessage();
            $message->save();
            $this->realtimePublisher->emitStatus($message);
        }
    }

    private function buildLocationMessage(ChatMessage $message): string
    {
        $lat = $message->metadata['location']['lat'] ?? $message->metadata['location']['latitude'] ?? null;
        $lng = $message->metadata['location']['lng'] ?? $message->metadata['location']['longitude'] ?? null;
        $label = $message->metadata['location']['name'] ?? $message->metadata['location']['address'] ?? 'Localização';

        if ($lat && $lng) {
            return sprintf('%s: https://maps.google.com/?q=%s,%s', $label, $lat, $lng);
        }

        return $message->content ?: 'Localização enviada';
    }

    private function fallbackCaption(ChatMessage $message): ?string
    {
        if ($message->content) {
            return $message->content;
        }

        return match ($message->type) {
            'image' => 'Imagem',
            'video' => 'Vídeo',
            'sticker' => '🧩 Sticker',
            'document' => 'Documento',
            default => null,
        };
    }

    private function buildOutboundText(ChatMessage $message, ?ChatInstance $instance): string
    {
        $content = (string) ($message->content ?? '');

        if (! $this->shouldPrefixAttendantName($message, $instance)) {
            return $content;
        }

        $attendantName = $this->resolveAttendantName($message);
        if ($attendantName === null) {
            return $content;
        }

        return sprintf("%s:\n%s", $attendantName, $content);
    }

    private function shouldPrefixAttendantName(ChatMessage $message, ?ChatInstance $instance): bool
    {
        $allowedSources = [ChatMessageDTO::SOURCE_AGENT, ChatMessageDTO::SOURCE_BOT, 'ai'];

        if ($message->type !== 'text' || $message->direction !== 'outgoing' || ! in_array($message->source, $allowedSources, true)) {
            return false;
        }

        if (! $instance instanceof ChatInstance) {
            return false;
        }

        $settings = $instance->settings_json ?? [];

        return (bool) ($settings['send_attendant_name'] ?? false);
    }

    private function resolveAttendantName(ChatMessage $message): ?string
    {
        if (in_array($message->source, [ChatMessageDTO::SOURCE_BOT, 'ai'], true)) {
            $metadata = $message->metadata ?? [];
            $agentName = $metadata['ai_agent_name'] ?? null;
            if (is_string($agentName) && trim($agentName) !== '') {
                return trim($agentName);
            }

            return 'Assistente Virtual';
        }

        $message->loadMissing('user:id,name');
        $user = $message->user;

        if (! $user instanceof AuthUser) {
            return null;
        }

        $name = trim((string) $user->name);

        return $name !== '' ? $name : null;
    }

    /**
     * @return array{file?: string}
     */
    private function resolveFilePayload(ChatMessage $message): array
    {
        $fileUrl = $message->file_url;

        if (! $fileUrl) {
            return [];
        }

        $appUrl = (string) config('app.url', 'http://localhost');
        $isLocal = str_starts_with($fileUrl, $appUrl.'/storage/')
            || str_starts_with($fileUrl, '/storage/')
            || str_contains($fileUrl, 'localhost');

        if ($isLocal) {
            $storagePath = $this->extractStoragePath($fileUrl);

            if ($storagePath && Storage::disk('public')->exists($storagePath)) {
                $contents = Storage::disk('public')->get($storagePath);
                $detectedMime = Storage::disk('public')->mimeType($storagePath);
                $mimeType = $message->mime_type ?? ($detectedMime !== false ? $detectedMime : 'application/octet-stream');

                return [
                    'file' => 'data:'.$mimeType.';base64,'.base64_encode((string) $contents),
                ];
            }
        }

        return ['file' => $fileUrl];
    }

    private function extractStoragePath(string $url): ?string
    {
        if (str_contains($url, '/storage/')) {
            $path = Str::after($url, '/storage/');

            return $path !== '' ? $path : null;
        }

        return null;
    }

    private function resolveMediaType(ChatMessage $message): string
    {
        $type = $message->type;
        $mimeType = $message->mime_type ?? '';

        if (in_array($type, ['image', 'video', 'sticker', 'document'], true)) {
            return $type;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if ($message->file_url) {
            return 'document';
        }

        return 'image';
    }

    private function resolveInstanceToken(?ChatInstance $instance): ?string
    {
        if (! $instance instanceof ChatInstance) {
            return null;
        }

        if ($instance->provider === 'zapi') {
            $settings = $instance->settings_json ?? [];
            $instanceId = $settings['instance_id']
                ?? $settings['instance']
                ?? $settings['instanceId']
                ?? null;
            $tokenId = $settings['token_id']
                ?? $settings['token']
                ?? $settings['tokenId']
                ?? null;

            if ($instanceId && $tokenId) {
                return $instanceId.':'.$tokenId;
            }

            return null;
        }

        return $instance->webhook_token;
    }
}
