<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Jobs\SubmitMetaTemplateJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Validation\ValidationException;

/**
 * Cria um template de mensagem Meta localmente como `pending` e dispara
 * job assíncrono de submissão à Meta WhatsApp Cloud API via Gateway.
 *
 * Regras:
 * - `chat_instance_id` deve pertencer ao tenant e ter `provider='meta'`.
 * - Registro local nasce com `status='pending'` e `external_id=null`.
 * - O job atualiza `external_id` + `status` quando a Meta responde.
 *
 * Estrutura esperada de $data:
 *   - chat_instance_id (string, uuid)
 *   - name (string)
 *   - language (string, default pt_BR)
 *   - category (string, ex.: MARKETING, UTILITY, AUTHENTICATION)
 *   - components (array<int, array<string, mixed>>)
 *   - shortcut (string, opcional)
 */
final readonly class CreateMetaTemplateAction
{
    /**
     * Cria template Meta local com status 'pending' e despacha job de submissão assíncrono.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $data  Dados do template (chat_instance_id, name, language, category, components, shortcut).
     * @return ChatMessageTemplate Template criado com status 'pending'.
     *
     * @throws \Illuminate\Validation\ValidationException Se chat_instance_id ou name forem inválidos.
     */
    public function execute(string $tenantId, array $data): ChatMessageTemplate
    {
        $instanceId = isset($data['chat_instance_id']) && is_string($data['chat_instance_id'])
            ? $data['chat_instance_id']
            : '';

        if ($instanceId === '') {
            throw ValidationException::withMessages([
                'chat_instance_id' => 'Campo obrigatório.',
            ]);
        }

        $instance = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $instanceId)
            ->where('provider', 'meta')
            ->first();

        if (! $instance instanceof ChatInstance) {
            throw ValidationException::withMessages([
                'chat_instance_id' => 'Instância Meta não encontrada para o tenant.',
            ]);
        }

        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
        $language = isset($data['language']) && is_string($data['language']) && $data['language'] !== ''
            ? $data['language']
            : 'pt_BR';
        $category = isset($data['category']) && is_string($data['category']) ? $data['category'] : null;
        $components = isset($data['components']) && is_array($data['components']) ? $data['components'] : [];
        $shortcut = isset($data['shortcut']) && is_string($data['shortcut']) && $data['shortcut'] !== ''
            ? $data['shortcut']
            : '/'.substr(str_replace([' ', '-'], '_', strtolower($name)), 0, 60);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Campo obrigatório.',
            ]);
        }

        $template = ChatMessageTemplate::query()->create([
            'tenant_id' => $tenantId,
            'chat_instance_id' => (string) $instance->id,
            'provider' => 'meta',
            'name' => $name,
            'language' => $language,
            'status' => 'pending',
            'external_id' => null,
            'rejected_reason' => null,
            'components_json' => $components,
            'category' => $category,
            'content' => $this->extractBodyText($components),
            'shortcut' => $shortcut,
            'is_active' => true,
        ]);

        SubmitMetaTemplateJob::dispatch((string) $template->id, (string) $template->tenant_id);

        return $template->refresh();
    }

    /**
     * Extrai o texto do componente BODY da lista de components do template.
     *
     * @param  array<int|string, mixed>  $components  Components do template Meta.
     * @return string Texto do body ou string vazia se não encontrado.
     */
    private function extractBodyText(array $components): string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = isset($component['type']) && is_string($component['type'])
                ? strtoupper($component['type'])
                : '';
            if ($type === 'BODY') {
                return isset($component['text']) && is_string($component['text']) ? $component['text'] : '';
            }
        }

        return '';
    }
}
