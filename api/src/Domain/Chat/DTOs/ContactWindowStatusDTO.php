<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * DTO para status da janela de atendimento (24h/72h CTWA) de um contato.
 *
 * Indica se o contato está dentro da janela de mensagens permitidas
 * para envio de texto livre (vs template obrigatório).
 *
 * @category DTOs
 */
final readonly class ContactWindowStatusDTO
{
    /**
     * @param  bool  $canSendFreeText  True se pode enviar texto livre, false se precisa usar template.
     * @param  Carbon|null  $lastMessageAt  Data/hora da última mensagem do contato (is_from_contact=true).
     * @param  Carbon|null  $expiresAt  Expiração absoluta da janela corrente (campo persistido ou calculada por fallback).
     * @param  string|null  $windowType  Tipo da janela corrente ('24h'|'72h'), quando conhecido.
     */
    public function __construct(
        public bool $canSendFreeText,
        public ?Carbon $lastMessageAt,
        public ?Carbon $expiresAt = null,
        public ?string $windowType = null,
    ) {}

    /**
     * Cria uma instância do DTO a partir de um array associativo.
     *
     * @param  array<string, mixed>  $data  Dados do status.
     */
    public static function from(array $data): self
    {
        return new self(
            canSendFreeText: (bool) $data['can_send_free_text'],
            lastMessageAt: isset($data['last_message_at'])
                ? Carbon::parse($data['last_message_at'])
                : null,
            expiresAt: isset($data['expires_at'])
                ? Carbon::parse($data['expires_at'])
                : null,
            windowType: $data['window_type'] ?? null,
        );
    }

    /**
     * Cria uma instância a partir de um Request HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            canSendFreeText: (bool) $request->input('can_send_free_text', false),
            lastMessageAt: $request->has('last_message_at')
                ? Carbon::parse($request->input('last_message_at'))
                : null,
            expiresAt: $request->has('expires_at')
                ? Carbon::parse($request->input('expires_at'))
                : null,
            windowType: $request->input('window_type'),
        );
    }

    /**
     * Converte o DTO em um array associativo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'canSendFreeText' => $this->canSendFreeText,
            'lastMessageAt' => $this->lastMessageAt?->toIso8601String(),
            'expiresAt' => $this->expiresAt?->toIso8601String(),
            'windowType' => $this->windowType,
        ];
    }
}
