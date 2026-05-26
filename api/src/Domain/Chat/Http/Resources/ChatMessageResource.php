<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de Mensagem de Chat.
 *
 * Transforma a entidade ChatMessage no formato esperado pelo frontend,
 * incluindo resolução de mensagens citadas e compactação de metadados.
 */
final class ChatMessageResource extends BaseJsonResource
{
    /**
     * Mapa pré-carregado de mensagens citadas para evitar consultas N+1.
     *
     * @var array<string, \Domain\Chat\Models\ChatMessage>
     */
    private array $quotedMap = [];

    /**
     * Definir o mapa pré-carregado de mensagens citadas.
     *
     * @param  array<string, \Domain\Chat\Models\ChatMessage>  $map
     */
    public function withQuotedMap(array $map): static
    {
        $this->quotedMap = $map;

        return $this;
    }

    /**
     * Transforma a entidade no array de resposta da API.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        $fileUrl = $this->file_url;
        if (is_string($fileUrl) && str_starts_with($fileUrl, '/storage/')) {
            $fileUrl = rtrim($request->getSchemeAndHttpHost(), '/').$fileUrl;
        }

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'contact_id' => $this->contact_id,
            'content' => $this->content,
            'type' => $this->type,
            'direction' => $this->direction,
            'is_from_contact' => $this->is_from_contact,
            'status' => $this->status,
            'file_url' => $fileUrl,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'external_id' => $this->external_id,
            'metadata' => $this->compactMetadata($request),
            'quoted_message' => $this->resolveQuotedMessage(),
            'error_message' => $this->error_message,
            'sent_at' => $this->iso($this->sent_at),
            'delivered_at' => $this->iso($this->delivered_at),
            'read_at' => $this->iso($this->read_at),
            'reactions' => $this->reactions,
            'is_edited' => $this->is_edited,
            'edited_at' => $this->iso($this->edited_at),
            'edit_history' => $this->edit_history,
            'is_deleted' => $this->is_deleted,
            'deleted_at' => $this->iso($this->deleted_at),
            'deleted_by' => $this->deleted_by,
            'media_transcription' => $this->media_transcription,
            'media_transcription_status' => $this->media_transcription_status,
            'media_transcription_provider' => $this->media_transcription_provider,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    /**
     * Compactar metadados para reduzir o payload nas listagens.
     *
     * Para depuração, o payload completo pode ser solicitado com `?include_full_metadata=1`.
     *
     * @return array<string, mixed>|null
     */
    private function compactMetadata(Request $request): ?array
    {
        $metadata = $this->metadata;
        if (! is_array($metadata)) {
            return null;
        }

        if ($request->boolean('include_full_metadata')) {
            return $metadata;
        }

        $message = is_array($metadata['message'] ?? null) ? $metadata['message'] : [];
        $gatewayResponse = is_array($metadata['gateway_response'] ?? null) ? $metadata['gateway_response'] : [];
        $contact = $this->normalizeMetadataValue($metadata['contact'] ?? null);
        $location = $this->normalizeMetadataValue($metadata['location'] ?? null);

        $compact = [
            'is_auto_reply' => $metadata['is_auto_reply'] ?? null,
            'autopilot_run_id' => $metadata['autopilot_run_id'] ?? null,
            'quoted_message_id' => $metadata['quoted_message_id'] ?? null,
            'provider' => $metadata['provider'] ?? null,
            'event_type' => $metadata['event_type'] ?? null,
            'direction' => $metadata['direction'] ?? null,
            'instance_id' => $metadata['instance_id'] ?? null,
            'message' => [
                'id' => $message['id'] ?? null,
                'type' => $message['type'] ?? null,
                'chatid' => $message['chatid'] ?? null,
                'fromMe' => $message['fromMe'] ?? null,
                'timestamp' => $message['timestamp'] ?? null,
                'body' => $message['body'] ?? null,
                'text' => $message['text'] ?? null,
            ],
            'gateway_response' => [
                'id' => $gatewayResponse['id'] ?? null,
                'status' => $gatewayResponse['status'] ?? null,
                'messageid' => $gatewayResponse['messageid'] ?? null,
                'chatid' => $gatewayResponse['chatid'] ?? null,
                'fromMe' => $gatewayResponse['fromMe'] ?? null,
                'messageTimestamp' => $gatewayResponse['messageTimestamp'] ?? null,
            ],
            'contact' => $contact,
            'location' => $location,
        ];

        return $this->pruneNulls($compact);
    }

    /**
     * Remover recursivamente valores nulos do array.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function pruneNulls(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->pruneNulls($item);
                if ($value[$key] === []) {
                    unset($value[$key]);
                }

                continue;
            }

            if ($item === null) {
                unset($value[$key]);
            }
        }

        return $value;
    }

    /**
     * Normalizar valores de metadados que podem vir como string JSON serializada.
     */
    private function normalizeMetadataValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = $value;
        for ($i = 0; $i < 2; $i++) {
            $decoded = json_decode($normalized, true);
            if (! is_array($decoded)) {
                break;
            }
            $normalized = $decoded;
        }

        return $normalized;
    }

    /**
     * Resolver os dados da mensagem citada para exibição no frontend.
     *
     * Utiliza o mapa pré-carregado quando disponível, evitando consultas N+1.
     *
     * @return array<string, mixed>|null
     */
    private function resolveQuotedMessage(): ?array
    {
        $metadata = $this->metadata ?? [];

        // ID externo da mensagem citada (vem do webhook do UAZAPI)
        $quotedExternalId = $metadata['message']['quoted'] ?? null;
        if (! $quotedExternalId) {
            // Tentar buscar do quoted_message_id (quando enviado pelo nosso sistema)
            $quotedMessageId = $metadata['quoted_message_id'] ?? null;
            if ($quotedMessageId) {
                $currentTenantId = $this->tenant_id ?? $this->resource->tenant_id ?? null;
                $quotedMessage = $this->quotedMap[$quotedMessageId]
                    ?? ($currentTenantId
                        ? \Domain\Chat\Models\ChatMessage::query()
                            ->where('tenant_id', $currentTenantId)
                            ->find($quotedMessageId)
                        : null);
                if ($quotedMessage) {
                    return $this->formatQuotedMessage($quotedMessage);
                }
            }

            return null;
        }

        // Buscar mensagem pelo external_id (mapa primeiro, fallback query)
        $quotedMessage = $this->quotedMap[$quotedExternalId]
            ?? \Domain\Chat\Models\ChatMessage::query()
                ->where('tenant_id', $this->tenant_id)
                ->where('external_id', $quotedExternalId)
                ->first();

        if ($quotedMessage) {
            return $this->formatQuotedMessage($quotedMessage);
        }

        // Fallback: extrair do contextInfo do webhook
        $contextInfo = $metadata['message']['content']['contextInfo'] ?? null;
        if ($contextInfo) {
            $quotedContent = $contextInfo['quotedMessage']['conversation']
                ?? $contextInfo['quotedMessage']['extendedTextMessage']['text']
                ?? null;
            $participant = $contextInfo['participant'] ?? null;

            return [
                'id' => null,
                'external_id' => $quotedExternalId,
                'content' => $quotedContent,
                'type' => 'text',
                'direction' => null,
                'sender_name' => $participant ? explode('@', $participant)[0] : null,
                'file_name' => null,
                'mime_type' => null,
            ];
        }

        return null;
    }

    /**
     * Formatar uma mensagem citada para o payload de resposta.
     *
     * @return array<string, mixed>
     */
    private function formatQuotedMessage(\Domain\Chat\Models\ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'external_id' => $message->external_id,
            'content' => $message->content,
            'type' => $message->type,
            'direction' => $message->direction,
            'sender_name' => $message->direction === 'outgoing' ? 'Você' : null,
            'file_name' => $message->file_name,
            'mime_type' => $message->mime_type,
        ];
    }
}
