<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sincroniza templates de mensagem da Meta (WhatsApp Cloud API) para a base local.
 *
 * Busca a lista de templates da instância via Gateway e faz upsert em
 * `chat_message_templates` por `(chat_instance_id, name, language)`. Templates
 * locais cuja chave não vier mais na resposta são marcados como `disabled`.
 * Templates com `provider='local'` nunca são tocados.
 */
final readonly class SyncMetaTemplatesAction
{
    /**
     * Sincroniza os templates da Meta para a instância informada.
     *
     * @return int Quantidade de templates criados ou atualizados (exclui os marcados como disabled).
     */
    public function execute(string $tenantId, string $chatInstanceId): int
    {
        $instance = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $chatInstanceId)
            ->where('provider', 'meta')
            ->firstOrFail();

        $instanceId = (string) $instance->id;

        $baseUrl = rtrim($this->stringConfig('gateway.url'), '/');
        $secret = $this->stringConfig('gateway.secret');

        $response = Http::withHeaders(['x-api-key' => $secret])
            ->get($baseUrl.'/channels/'.$instanceId.'/templates', [
                'include_all' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao buscar templates da Meta (HTTP '.$response->status().').'
            );
        }

        $rawTemplates = $response->json('data');
        /** @var array<int, mixed> $templates */
        $templates = is_array($rawTemplates) ? $rawTemplates : [];

        $now = Carbon::now();

        /** @var list<string> $syncedKeys */
        $syncedKeys = [];
        $count = 0;

        foreach ($templates as $template) {
            if (! is_array($template)) {
                continue;
            }

            $name = $this->stringValue($template, 'name');
            $language = $this->stringValue($template, 'language');

            if ($name === '' || $language === '') {
                continue;
            }

            $components = isset($template['components']) && is_array($template['components'])
                ? $template['components']
                : [];

            $status = strtolower($this->stringValue($template, 'status', 'pending'));

            $existing = ChatMessageTemplate::query()
                ->where('chat_instance_id', $instanceId)
                ->where('name', $name)
                ->where('language', $language)
                ->first();

            $payload = [
                'tenant_id' => $tenantId,
                'chat_instance_id' => $instanceId,
                'provider' => 'meta',
                'name' => $name,
                'language' => $language,
                'status' => $status,
                'rejected_reason' => $this->nullableString($template, 'rejected_reason'),
                'external_id' => $this->nullableString($template, 'external_id'),
                'components_json' => $components,
                'category' => $this->nullableString($template, 'category'),
                'content' => $this->extractBodyText($components),
                'last_synced_at' => $now,
            ];

            if ($existing === null) {
                $payload['is_active'] = true;
                $payload['shortcut'] = '/'.substr(
                    str_replace([' ', '-'], '_', strtolower($name)),
                    0,
                    60
                );
                ChatMessageTemplate::query()->create($payload);
            } else {
                $existing->fill($payload)->save();
            }

            $syncedKeys[] = $name.'|'.$language;
            $count++;
        }

        $stale = ChatMessageTemplate::query()
            ->where('chat_instance_id', $instanceId)
            ->where('provider', 'meta')
            ->where('status', '!=', 'disabled')
            ->get(['id', 'name', 'language']);

        $staleIds = $stale
            ->reject(function (ChatMessageTemplate $t) use ($syncedKeys): bool {
                $name = is_string($t->name) ? $t->name : '';
                $language = is_string($t->language) ? $t->language : '';

                return in_array($name.'|'.$language, $syncedKeys, true);
            })
            ->pluck('id')
            ->all();

        if ($staleIds !== []) {
            ChatMessageTemplate::query()
                ->whereIn('id', $staleIds)
                ->update([
                    'status' => 'disabled',
                    'last_synced_at' => $now,
                ]);
        }

        return $count;
    }

    /**
     * Extrai o texto do componente BODY do array de components da Meta.
     *
     * @param  array<int|string, mixed>  $components
     */
    private function extractBodyText(array $components): string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper($this->stringValue($component, 'type'));
            if ($type === 'BODY') {
                return $this->stringValue($component, 'text');
            }
        }

        return '';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function stringValue(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }
}
