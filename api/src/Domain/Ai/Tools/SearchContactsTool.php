<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMContact;
use Domain\Shared\Support\SearchSanitizer;

/**
 * Ferramenta de IA para busca difusa de contatos no CRM.
 *
 * Input esperado: query (obrigatório) e limit opcional (1-50).
 * Output produzido: lista de contatos com id, nome, email, telefone e WhatsApp.
 * Quando usar: identificar se o cliente já existe no CRM antes de criar um novo contato.
 */
class SearchContactsTool implements AiToolInterface
{
    /** Executa a busca de contatos pelo termo informado. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $query = trim((string) ($input->parameters['query'] ?? ''));
        $tenantId = (string) ($input->context['tenant_id'] ?? '');
        $limit = min(50, max(1, (int) ($input->parameters['limit'] ?? 10)));

        if ($query === '') {
            return ToolResultDTO::failure('Query is required');
        }

        $contacts = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', SearchSanitizer::likeContains($query))
                    ->orWhere('email', 'like', SearchSanitizer::likeContains($query))
                    ->orWhere('phone', 'like', SearchSanitizer::likeContains($query))
                    ->orWhere('whatsapp', 'like', SearchSanitizer::likeContains($query))
                    ->orWhere('document', 'like', SearchSanitizer::likeContains($query));
            })
            ->limit($limit)
            ->get();

        return ToolResultDTO::success(
            message: sprintf('%d contacts found', $contacts->count()),
            data: [
                'contacts' => $contacts->map(static fn (CRMContact $contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'whatsapp' => $contact->whatsapp,
                ])->values()->all(),
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Search contacts by name, email, phone, whatsapp or document.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search term for fuzzy matching',
            ],
            'limit' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum number of contacts (1-50)',
            ],
        ];
    }
}
