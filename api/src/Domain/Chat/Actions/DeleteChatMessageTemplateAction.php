<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Remove um template de mensagem.
 *
 * Regras:
 * - `provider='local'`: soft delete direto.
 * - `provider='meta'`: chama Gateway DELETE para remover na Meta. Em sucesso,
 *   soft delete. Em falha, marca `status='disabled'` + soft delete (não bloqueia
 *   o usuário; o gateway pode estar fora do ar e o registro local fica inerte).
 */
final readonly class DeleteChatMessageTemplateAction
{
    public function execute(ChatMessageTemplate $template): void
    {
        if ($template->provider !== 'meta') {
            $template->delete();

            return;
        }

        $instanceId = is_string($template->chat_instance_id) ? $template->chat_instance_id : '';
        $name = is_string($template->name) ? $template->name : '';

        $baseUrl = rtrim($this->stringConfig('gateway.url'), '/');
        $secret = $this->stringConfig('gateway.secret');

        $remoteOk = false;

        if ($instanceId !== '' && $name !== '') {
            try {
                $response = Http::withHeaders(['x-api-key' => $secret])
                    ->delete($baseUrl.'/channels/'.$instanceId.'/templates/'.$name);

                $remoteOk = $response->successful();

                if (! $remoteOk) {
                    Log::warning('Falha ao deletar template Meta no Gateway', [
                        'template_id' => (string) $template->id,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Exceção ao deletar template Meta no Gateway', [
                    'template_id' => (string) $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $remoteOk) {
            $template->forceFill(['status' => 'disabled'])->save();
        }

        $template->delete();
    }

    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }
}
