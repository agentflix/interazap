<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles automated outbound ticket messages and evaluation flows.
 */
final class ChatTicketAutomationService
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
    ) {}

    public function maybeCreateEvaluation(ChatTicket $ticket): void
    {
        if (! $ticket->instance_id) {
            return;
        }

        $instance = ChatInstance::query()
            ->select(['id', 'tenant_id', 'provider', 'evaluation_enabled', 'webhook_token', 'settings_json'])
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->instance_id);

        if (! $instance || ! $instance->evaluation_enabled) {
            return;
        }

        $evaluation = ChatTicketEvaluation::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'tenant_id' => $ticket->tenant_id],
            [
                'token' => (string) Str::orderedUuid(),
                'rating' => 0,
                'comment' => null,
                'submitted_at' => null,
            ],
        );

        $phone = $this->resolveDestinationPhone($ticket);
        $token = $this->resolveGatewayToken($instance);

        if ($instance->provider !== 'uazapi' || ! $phone || ! $token) {
            return;
        }

        $evaluationBaseUrl = (string) config('chat.evaluation_public_url', 'http://localhost:4200');
        $evaluationUrl = rtrim($evaluationBaseUrl, '/').'/chat/evaluations/'.$evaluation->token;
        $message = 'Olá! Seu atendimento foi encerrado. Gostaríamos da sua avaliação: '.$evaluationUrl;

        $chatMessage = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $ticket->tenant_id,
            'ticket_id' => (string) $ticket->id,
            'user_id' => $ticket->assigned_to,
            'contact_id' => $ticket->contact_id,
            'content' => $message,
            'type' => 'text',
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => 'system',
            'status' => 'pending',
            'metadata' => [
                'kind' => 'evaluation_invitation',
                'evaluation_id' => (string) $evaluation->id,
                'evaluation_token' => (string) $evaluation->token,
                'evaluation_url' => $evaluationUrl,
            ],
        ]);

        try {
            $response = $this->gateway->sendText((string) $token, [
                'number' => $phone,
                'text' => $message,
            ]);

            $chatMessage->status = 'sent';
            $chatMessage->sent_at = now();
            $chatMessage->external_id = $response['messageid'] ?? $response['id'] ?? null;
            $chatMessage->save();
        } catch (\Throwable $exception) {
            $chatMessage->loadMissing('extended');
            $chatMessage->status = 'failed';
            $chatMessage->error_message = $exception->getMessage();
            $chatMessage->save();

            Log::warning('chat.evaluation_link_send_failed', [
                'tenant_id' => (string) $ticket->tenant_id,
                'ticket_id' => (string) $ticket->id,
                'instance_id' => (string) $instance->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extraMetadata
     */
    public function sendConfiguredSystemMessage(
        ChatTicket $ticket,
        string $flagKey,
        string $messageKey,
        string $kind,
        array $extraMetadata = [],
    ): void {
        if (! $ticket->instance_id) {
            return;
        }

        $instance = ChatInstance::query()
            ->select(['id', 'tenant_id', 'provider', 'mode', 'webhook_token', 'settings_json'])
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->instance_id);

        if (! $instance) {
            return;
        }

        $settings = is_array($instance->settings_json) ? $instance->settings_json : [];
        $enabled = (bool) ($settings[$flagKey] ?? false);
        $message = trim((string) ($settings[$messageKey] ?? ''));

        if (! $enabled || $message === '') {
            return;
        }

        $phone = $this->resolveDestinationPhone($ticket);
        $token = $this->resolveGatewayToken($instance);

        if (! $phone || ! $token) {
            return;
        }

        $chatMessage = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $ticket->tenant_id,
            'ticket_id' => (string) $ticket->id,
            'user_id' => $ticket->assigned_to,
            'contact_id' => $ticket->contact_id,
            'content' => $message,
            'type' => 'text',
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => 'system',
            'status' => 'pending',
            'metadata' => [
                'kind' => $kind,
                ...$extraMetadata,
            ],
        ]);

        try {
            if ($instance->provider === 'zapi') {
                $response = $this->gateway->sendOutboundMessage(
                    'zapi',
                    (string) $token,
                    (string) $ticket->tenant_id,
                    (string) $instance->id,
                    [
                        'type' => 'text',
                        'to' => $phone,
                        'text' => $message,
                    ],
                );
            } else {
                $response = $this->gateway->sendText((string) $token, [
                    'number' => $phone,
                    'text' => $message,
                ]);
            }

            $chatMessage->status = 'sent';
            $chatMessage->sent_at = now();
            $chatMessage->external_id = $response['messageid'] ?? $response['id'] ?? null;
            $chatMessage->metadata = array_merge($chatMessage->metadata ?? [], ['gateway_response' => $response]);
            $chatMessage->save();
        } catch (\Throwable $exception) {
            $chatMessage->loadMissing('extended');
            $chatMessage->status = 'failed';
            $chatMessage->error_message = $exception->getMessage();
            $chatMessage->save();

            Log::warning('chat.automated_message_send_failed', [
                'tenant_id' => (string) $ticket->tenant_id,
                'ticket_id' => (string) $ticket->id,
                'instance_id' => (string) $instance->id,
                'kind' => $kind,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function isAiBusinessHoursMode(ChatTicket $ticket): bool
    {
        if (! $ticket->instance_id) {
            return false;
        }

        $instance = ChatInstance::query()
            ->select(['id', 'tenant_id', 'mode', 'settings_json'])
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->instance_id);

        if (! $instance) {
            return false;
        }

        $candidates = [];
        if (is_string($instance->mode) && $instance->mode !== '') {
            $candidates[] = mb_strtolower($instance->mode);
        }

        $settings = is_array($instance->settings_json) ? $instance->settings_json : [];
        foreach (['mode', 'operation_mode', 'service_mode', 'attendance_mode', 'ai_mode'] as $key) {
            $value = $settings[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $candidates[] = mb_strtolower($value);
            }
        }

        foreach ($candidates as $candidate) {
            $hasAi = str_contains($candidate, 'ia') || str_contains($candidate, 'ai');
            $hasBusinessHours = str_contains($candidate, 'horario')
                || str_contains($candidate, 'exped')
                || str_contains($candidate, 'business');

            if ($hasAi && $hasBusinessHours) {
                return true;
            }
        }

        return false;
    }

    public function resolveDestinationPhone(ChatTicket $ticket): ?string
    {
        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;

        if (! $number && $ticket->contact) {
            $number = $ticket->contact->whatsapp ?? $ticket->contact->phone;
        }

        if (! is_string($number) || $number === '') {
            return null;
        }

        $normalized = str_contains($number, '@') ? Str::before($number, '@') : $number;
        $digits = preg_replace('/[^0-9]/', '', $normalized);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return $digits;
    }

    public function resolveGatewayToken(ChatInstance $instance): ?string
    {
        $settingsToken = $instance->settings_json['token'] ?? null;

        if (is_string($settingsToken) && $settingsToken !== '') {
            return $settingsToken;
        }

        return $instance->webhook_token !== '' ? $instance->webhook_token : null;
    }
}
