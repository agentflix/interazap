<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Submete um template de mensagem Meta (criado localmente como `pending`)
 * para a WhatsApp Cloud API via Gateway. Atualiza `external_id` + `status`
 * conforme retorno. Em falha definitiva, marca como `rejected`.
 */
final class SubmitMetaTemplateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  string  $templateId  ID do template no banco de dados local.
     * @param  string  $tenantId  Identificador do tenant dono do template.
     */
    public function __construct(
        private readonly string $templateId,
        private readonly string $tenantId,
    ) {
        $this->onQueue('meta-templates');
    }

    /**
     * Backoff exponencial: 30s, 120s, 300s.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Submete o template ao Gateway e atualiza `external_id` e `status` conforme resposta.
     */
    public function handle(): void
    {
        $template = ChatMessageTemplate::query()->where('tenant_id', $this->tenantId)->find($this->templateId);

        if (! $template instanceof ChatMessageTemplate) {
            Log::warning('[SubmitMetaTemplateJob] Template não encontrado', [
                'template_id' => $this->templateId,
            ]);

            return;
        }

        if ($template->provider !== 'meta') {
            return;
        }

        $instanceId = is_string($template->chat_instance_id) ? $template->chat_instance_id : '';
        if ($instanceId === '') {
            $this->markRejected($template, 'chat_instance_id ausente');

            return;
        }

        $baseUrl = rtrim($this->stringConfig('gateway.url'), '/');
        $secret = $this->stringConfig('gateway.secret');

        $components = is_array($template->components_json) ? $template->components_json : [];
        $name = is_string($template->name) ? $template->name : '';
        $language = is_string($template->language) ? $template->language : 'pt_BR';
        $category = is_string($template->category) ? $template->category : 'UTILITY';

        $response = Http::withHeaders(['x-api-key' => $secret])
            ->timeout(20)
            ->post($baseUrl.'/channels/'.$instanceId.'/templates', [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'components' => $components,
            ]);

        if (! $response->successful()) {
            $message = $response->json('message');
            $reason = is_string($message) ? $message : $response->body();
            Log::warning('[SubmitMetaTemplateJob] Gateway respondeu com erro', [
                'template_id' => $this->templateId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Tenta novamente até atingir tries; quando estourar, failed() marca rejected.
            throw new \RuntimeException('Falha ao submeter template Meta: HTTP '.$response->status().' '.$reason);
        }

        $payload = is_array($response->json()) ? $response->json() : [];
        $externalId = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : null;
        if ($externalId === null && isset($payload['external_id']) && is_string($payload['external_id'])) {
            $externalId = $payload['external_id'];
        }

        $statusRaw = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : 'pending';
        $status = strtolower($statusRaw);

        $template->forceFill([
            'external_id' => $externalId,
            'status' => $status,
            'rejected_reason' => null,
        ])->save();
    }

    /**
     * Trata a falha definitiva marcando o template como `rejected` com o motivo do erro.
     */
    public function failed(Throwable $exception): void
    {
        $template = ChatMessageTemplate::query()->where('tenant_id', $this->tenantId)->find($this->templateId);

        if (! $template instanceof ChatMessageTemplate) {
            return;
        }

        $this->markRejected($template, $exception->getMessage());
    }

    /**
     * Marca o template como rejeitado, registrando o motivo.
     */
    private function markRejected(ChatMessageTemplate $template, string $reason): void
    {
        $template->forceFill([
            'status' => 'rejected',
            'rejected_reason' => $reason,
        ])->save();
    }

    /**
     * Lê uma chave de configuração garantindo retorno do tipo string.
     */
    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }
}
