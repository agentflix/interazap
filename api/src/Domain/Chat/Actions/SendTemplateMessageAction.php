<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Envia uma mensagem usando um template Meta aprovado a partir de um ticket
 * existente. Reabre a janela 24h ao executar com sucesso.
 *
 * Regras:
 * - Template deve ser do mesmo tenant, `provider='meta'` e `status='approved'`.
 * - Ticket deve pertencer ao tenant; sua instância precisa ser Meta.
 * - Variáveis posicionais ({{1}}, {{2}}, ...) são substituídas em
 *   `components_json` antes do envio. As `BODY.parameters` da Meta são
 *   reconstruídas a partir de `$variables`.
 * - Cria `ChatMessage` outgoing/agent/template, dispara HTTP ao Gateway e
 *   emite evento WebSocket de nova mensagem (mesmo fluxo de outgoing).
 */
final readonly class SendTemplateMessageAction
{
    public function __construct(
        private ProcessChatMessageAction $processAction,
    ) {}

    /**
     * Envia template Meta aprovado para o contato do ticket.
     *
     * Valida template (Meta + aprovado), ticket (instância Meta) e resolve o número
     * de destino. Cria ChatMessage outgoing, chama o Gateway via HTTP e emite
     * evento WebSocket de nova mensagem.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket de destino.
     * @param  string  $templateId  Identificador do template.
     * @param  array<int|string, string>  $variables  Variáveis posicionais (1,2,...) ou indexadas.
     * @param  string|null  $userId  ID do usuário que disparou o envio.
     * @return ChatMessage Mensagem criada com status 'sent' ou 'failed'.
     *
     * @throws \Illuminate\Validation\ValidationException Se template, ticket ou instância forem inválidos.
     */
    public function execute(
        string $tenantId,
        string $ticketId,
        string $templateId,
        array $variables = [],
        ?string $userId = null,
    ): ChatMessage {
        $template = ChatMessageTemplate::query()
            ->where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $template instanceof ChatMessageTemplate) {
            throw ValidationException::withMessages([
                'template_id' => 'Template não encontrado.',
            ]);
        }

        if ($template->provider !== 'meta') {
            throw ValidationException::withMessages([
                'template_id' => 'Apenas templates Meta podem ser enviados por este endpoint.',
            ]);
        }

        if ($template->status !== 'approved') {
            throw ValidationException::withMessages([
                'template_id' => 'Template não está aprovado pela Meta.',
            ]);
        }

        $ticket = ChatTicket::query()
            ->where('id', $ticketId)
            ->where('tenant_id', $tenantId)
            ->with('contact')
            ->first();

        if (! $ticket instanceof ChatTicket) {
            throw ValidationException::withMessages([
                'ticket_id' => 'Ticket não encontrado.',
            ]);
        }

        $instance = $ticket->instance;
        if (! $instance instanceof ChatInstance || $instance->provider !== 'meta') {
            throw ValidationException::withMessages([
                'ticket_id' => 'Ticket não está vinculado a uma instância Meta.',
            ]);
        }

        $templateInstanceId = is_string($template->chat_instance_id) ? $template->chat_instance_id : '';
        $instanceId = (string) $instance->id;
        if ($templateInstanceId !== '' && $templateInstanceId !== $instanceId) {
            throw ValidationException::withMessages([
                'template_id' => 'Template não pertence à instância do ticket.',
            ]);
        }

        $to = $this->resolveDestinationNumber($ticket);
        if ($to === null) {
            throw ValidationException::withMessages([
                'ticket_id' => 'Não foi possível resolver número de destino.',
            ]);
        }

        $values = $this->normalizeVariables($variables);
        $components = is_array($template->components_json) ? $template->components_json : [];
        $renderedComponents = $this->renderComponents($components, $values);
        $renderedText = $this->renderBodyText($components, $values);

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'contact_id' => $ticket->contact_id,
            'user_id' => $userId,
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'type' => 'template',
            'content' => $renderedText,
            'status' => 'pending',
            'metadata' => [
                'template' => [
                    'id' => (string) $template->id,
                    'name' => $template->name,
                    'language' => ['code' => $template->language],
                    'components' => $renderedComponents,
                ],
                'template_variables' => $values,
            ],
        ]);

        $baseUrl = rtrim($this->stringConfig('gateway.url'), '/');
        $secret = $this->stringConfig('gateway.secret');

        try {
            $response = Http::withHeaders(['x-api-key' => $secret])
                ->timeout(30)
                ->post($baseUrl.'/channels/'.$instanceId.'/send-template', [
                    'to' => $to,
                    'template_name' => $template->name,
                    'language' => $template->language,
                    'components' => $renderedComponents,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Gateway respondeu HTTP '.$response->status().': '.$response->body()
                );
            }

            $payload = is_array($response->json()) ? $response->json() : [];
            $externalId = null;
            if (isset($payload['messageid']) && is_string($payload['messageid'])) {
                $externalId = $payload['messageid'];
            } elseif (isset($payload['id']) && is_string($payload['id'])) {
                $externalId = $payload['id'];
            }

            $message->status = 'sent';
            $message->sent_at = now();
            $message->external_id = $externalId;
            $message->metadata = array_merge($message->metadata ?? [], [
                'gateway_response' => $payload,
            ]);
            $message->save();
        } catch (\Throwable $e) {
            $message->status = 'failed';
            $message->error_message = $e->getMessage();
            $message->save();
        }

        $ticket->last_message_at = now();
        $ticket->save();

        $ticket->load(['latestMessage', 'contact']);
        $this->processAction->emitNewMessageEvent($message, $ticket);

        return $message;
    }

    /**
     * Resolve o número de destino do ticket em formato numérico puro.
     *
     * @param  ChatTicket  $ticket  Ticket com dados de contato.
     * @return string|null Número limpo ou null se não resolvível.
     */
    private function resolveDestinationNumber(ChatTicket $ticket): ?string
    {
        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;
        $contact = $ticket->contact;
        if (! $number && is_object($contact)) {
            $number = $contact->whatsapp ?? $contact->phone ?? null;
        }

        if (! is_string($number) || $number === '') {
            return null;
        }

        $number = str_contains($number, '@') ? Str::before($number, '@') : $number;
        $cleaned = preg_replace('/[^0-9]/', '', $number) ?? '';

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Normaliza variáveis para mapa indexado por string '1','2',...
     *
     * @param  array<int|string, mixed>  $variables
     * @return array<string, string>
     */
    private function normalizeVariables(array $variables): array
    {
        /** @var array<string, string> $result */
        $result = [];
        $position = 1;

        // Mantém a ordem natural de iteração: chaves numéricas viram posicionais.
        foreach ($variables as $key => $value) {
            $stringValue = is_scalar($value) ? (string) $value : '';
            $stringKey = (string) $key;

            if (is_int($key)) {
                $result[(string) $position] = $stringValue;
                $position++;

                continue;
            }

            // Aceita "1", "2", ... ou nomes; quando puramente numérico, usa como índice.
            if (preg_match('/^[0-9]+$/', $stringKey) === 1) {
                $result[$stringKey] = $stringValue;

                continue;
            }

            // Chaves não-numéricas viram posicionais conforme ordem.
            $result[(string) $position] = $stringValue;
            $position++;
        }

        // PHP converte chaves numericas em string para int automaticamente,
        // mas semanticamente queremos um mapa string => string.
        /** @var array<string, string> $typed */
        $typed = $result;

        return $typed;
    }

    /**
     * Substitui {{1}}, {{2}}, ... no texto BODY do template.
     *
     * @param  array<int|string, mixed>  $components
     * @param  array<string, string>  $values
     */
    private function renderBodyText(array $components, array $values): string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = isset($component['type']) && is_string($component['type'])
                ? strtoupper($component['type'])
                : '';
            if ($type !== 'BODY') {
                continue;
            }
            $text = isset($component['text']) && is_string($component['text']) ? $component['text'] : '';

            return $this->substitute($text, $values);
        }

        return '';
    }

    /**
     * Constrói os components.parameters da Meta a partir das variáveis posicionais.
     *
     * Sempre normaliza o componente BODY adicionando `parameters` no formato
     * `[{ type: 'text', text: ... }, ...]`. Outros componentes (HEADER, FOOTER,
     * BUTTONS) são preservados como vieram.
     *
     * @param  array<int|string, mixed>  $components
     * @param  array<string, string>  $values
     * @return list<array<string, mixed>>
     */
    private function renderComponents(array $components, array $values): array
    {
        $rendered = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = isset($component['type']) && is_string($component['type'])
                ? strtoupper($component['type'])
                : '';

            if ($type === 'BODY' && $values !== []) {
                $params = [];
                $position = 1;
                while (array_key_exists((string) $position, $values)) {
                    $params[] = [
                        'type' => 'text',
                        'text' => $values[(string) $position],
                    ];
                    $position++;
                }

                $component['parameters'] = $params;
            }

            $rendered[] = $component;
        }

        return $rendered;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function substitute(string $text, array $values): string
    {
        if ($values === []) {
            return $text;
        }

        return preg_replace_callback(
            '/\{\{\s*(\d+)\s*\}\}/',
            static function (array $matches) use ($values): string {
                $key = $matches[1];

                return $values[$key] ?? $matches[0];
            },
            $text
        ) ?? $text;
    }

    /**
     * Lê configuração como string, retornando vazio se não for string.
     *
     * @param  string  $key  Chave de configuração.
     * @return string Valor da configuração ou string vazia.
     */
    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }
}
