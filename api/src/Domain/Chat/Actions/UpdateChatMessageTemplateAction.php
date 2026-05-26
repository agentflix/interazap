<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Validation\ValidationException;

/**
 * Atualiza um template de mensagem.
 *
 * Regras:
 * - Templates `provider='local'`: aceitam todos os campos passados em $data.
 * - Templates `provider='meta'`: aceitam SOMENTE `is_active` e `shortcut`.
 *   Qualquer outro campo lança ValidationException (HTTP 422).
 */
final readonly class UpdateChatMessageTemplateAction
{
    /**
     * @var list<string>
     */
    private const META_EDITABLE_FIELDS = ['is_active', 'shortcut'];

    /**
     * Atualiza o template de mensagem com os dados fornecidos.
     *
     * Templates Meta aceitam apenas 'is_active' e 'shortcut'. Qualquer outro campo
     * lança ValidationException com HTTP 422.
     *
     * @param  ChatMessageTemplate  $template  Template a atualizar.
     * @param  array<string, mixed>  $data  Campos vindos do Request validado.
     * @return ChatMessageTemplate Template atualizado (após refresh).
     *
     * @throws \Illuminate\Validation\ValidationException Se campos proibidos forem enviados para template Meta.
     */
    public function execute(ChatMessageTemplate $template, array $data): ChatMessageTemplate
    {
        if ($template->provider === 'meta') {
            $invalid = array_diff(array_keys($data), self::META_EDITABLE_FIELDS);

            if ($invalid !== []) {
                throw ValidationException::withMessages([
                    'message' => 'Templates Meta não podem ser editados; exclua e recrie.',
                ]);
            }
        }

        $template->fill($data)->save();

        return $template->refresh();
    }
}
