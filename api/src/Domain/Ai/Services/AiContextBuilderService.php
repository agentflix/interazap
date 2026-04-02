<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Actions\CRMContactActions;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Str;

/**
 * Serviço de montagem de contexto para execuções de Autopilot.
 *
 * Constrói um payload estruturado com dados do ticket, contato
 * e histórico recente de mensagens para orientar a IA.
 *
 * @category Services
 */
final class AiContextBuilderService
{
    private const DEFAULT_HISTORY_LIMIT = 15;

    public function __construct(
        private readonly CRMContactActions $contactActions
    ) {}

    /**
     * Montar o contexto rico da conversa.
     *
     * @param  ChatTicket  $ticket  Ticket associado à conversa.
     * @param  ChatMessage  $currentMessage  Mensagem atual recebida.
     * @return array<string, mixed>
     */
    public function build(ChatTicket $ticket, ChatMessage $currentMessage): array
    {
        $ticket->loadMissing(['contact.company', 'extended']);

        $history = ChatMessage::query()
            ->where('ticket_id', $ticket->id)
            ->with('extended')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::DEFAULT_HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $formattedHistory = $history
            ->map(fn (ChatMessage $message) => $this->formatMessage($message))
            ->filter(fn (?string $value) => $value !== null && $value !== '')
            ->values()
            ->all();

        // Resolver contato do CRM com dados enriquecidos
        $contact = $this->resolveContact($ticket);

        $contactName = $contact instanceof CRMContact
            ? $contact->name
            : ($ticket->push_name ?? '');
        $contactPhone = $contact instanceof CRMContact
            ? ($contact->whatsapp ?? $contact->phone ?? '')
            : ($ticket->phone_e164 ?? $ticket->phone ?? '');

        return [
            'ticket' => [
                'id' => (string) $ticket->id,
                'protocol' => $ticket->protocol,
                'status' => $ticket->status,
                'department' => $ticket->category,
                'priority' => $ticket->priority,
                'channel' => $ticket->channel,
                'subject' => $ticket->subject,
            ],
            'contact' => [
                'name' => $contactName,
                'phone' => $contactPhone,
                'crm_data' => $contact instanceof CRMContact ? [
                    'contact_id' => (string) $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'company' => $contact->company?->name,
                ] : null,
            ],
            'conversation_history' => $formattedHistory,
            'current_input' => $this->resolveMessageContent($currentMessage),
            'user_context' => $this->getCRMSummary($contact, $ticket),
        ];
    }

    /**
     * Resolver contato do CRM a partir do ticket.
     *
     * 1. Verifica se o ticket já possui contact_id vinculado.
     * 2. Se não, tenta buscar pelo phone_e164 do ticket.
     *
     * @param  ChatTicket  $ticket  Ticket da conversa.
     * @return CRMContact|null Contato encontrado ou null.
     */
    private function resolveContact(ChatTicket $ticket): ?CRMContact
    {
        // Se já tem contact_id, carregar com relacionamentos de contexto
        if ($ticket->contact_id) {
            $contact = $ticket->contact;
            if ($contact instanceof CRMContact) {
                $contact->load([
                    'company',
                    'tags',
                    'negotiations' => function ($query): void {
                        $query->where('status', 'open')
                            ->with('step')
                            ->orderByDesc('created_at')
                            ->limit(3);
                    },
                ]);

                return $contact;
            }
        }

        // Tentar buscar pelo telefone
        $phoneE164 = $ticket->phone_e164 ?? $ticket->phone ?? null;
        if ($phoneE164 && $ticket->tenant_id) {
            return $this->contactActions->findByPhoneWithContext(
                (string) $ticket->tenant_id,
                $phoneE164
            );
        }

        return null;
    }

    /**
     * Montar resumo textual do CRM para contexto da IA.
     *
     * Formata informações do cliente de forma estruturada:
     * - Identificação: Nome e Empresa
     * - Perfil: Tags relevantes (VIP, Inadimplente, etc.)
     * - Negócios: Até 3 negociações em andamento
     *
     * @param  CRMContact|null  $contact  Contato do CRM (pode ser null).
     * @param  ChatTicket  $ticket  Ticket para fallback de informações.
     * @return string Resumo textual para o prompt da IA.
     */
    private function getCRMSummary(?CRMContact $contact, ChatTicket $ticket): string
    {
        if (! $contact) {
            $pushName = $ticket->push_name ?? '';

            return $pushName !== ''
                ? "Cliente Desconhecido (Novo Lead): {$pushName}"
                : 'Cliente Desconhecido (Novo Lead)';
        }

        $lines = [];

        // Identificação
        $identification = "Cliente Identificado: {$contact->name}";
        if ($contact->company) {
            $identification .= " (Empresa: {$contact->company->name})";
        }
        $lines[] = $identification;

        // Tags/Perfil
        if ($contact->relationLoaded('tags') && $contact->tags->isNotEmpty()) {
            $tagNames = $contact->tags->pluck('name')->implode(', ');
            $lines[] = "Perfil: {$tagNames}";
        }

        // Negociações abertas
        if ($contact->relationLoaded('negotiations') && $contact->negotiations->isNotEmpty()) {
            $lines[] = 'Oportunidades em aberto:';
            foreach ($contact->negotiations as $index => $negotiation) {
                $step = $negotiation->step;
                $stepName = $step instanceof \Domain\CRM\Models\CRMNegotiationFunnelStep ? $step->name : 'Sem etapa';
                $amount = number_format((float) $negotiation->amount, 2, ',', '.');
                $lines[] = sprintf(
                    '  %d. %s - %s (R$ %s)',
                    $index + 1,
                    $negotiation->title,
                    $stepName,
                    $amount
                );
            }
        }

        return implode("\n", $lines);
    }

    private function formatMessage(ChatMessage $message): ?string
    {
        $content = $this->resolveMessageContent($message);
        if ($content === '') {
            return null;
        }

        $actor = $message->is_from_contact || $message->direction === 'incoming'
            ? 'User'
            : 'Agent';

        return $actor.': '.$content;
    }

    private function resolveMessageContent(ChatMessage $message): string
    {
        $content = trim((string) ($message->content ?? ''));

        // Usar transcrição de mídia quando disponível
        $transcription = trim((string) ($message->media_transcription ?? ''));
        if ($transcription !== '') {
            $prefix = match ($message->type) {
                'image' => '[Imagem]',
                'audio' => '[Áudio transcrito]',
                'video' => '[Vídeo transcrito]',
                default => '[Mídia transcrita]',
            };

            return $content !== ''
                ? "{$content}\n{$prefix}: {$transcription}"
                : "{$prefix}: {$transcription}";
        }

        if ($content !== '') {
            return $content;
        }

        $label = match ($message->type) {
            'image' => 'Imagem enviada',
            'audio' => 'Áudio enviado',
            'location' => 'Localização enviada',
            'file' => 'Arquivo enviado',
            default => 'Anexo enviado',
        };

        if ($message->file_name) {
            $label .= ': '.Str::limit($message->file_name, 120, '...');
        }

        return $label;
    }
}
