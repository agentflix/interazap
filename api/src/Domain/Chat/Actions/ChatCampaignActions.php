<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatCampaignDTO;
use Domain\Chat\Models\ChatCampaign;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Casos de Uso para Campanhas de Chat.
 *
 * Centraliza a lógica de gestão de disparos em lote (campanhas),
 * incluindo o rastreamento de status, contagem de envios e persistência.
 *
 * @category Actions
 */
final class ChatCampaignActions
{
    /**
     * Listar todas as campanhas do tenant com paginação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return LengthAwarePaginator Paginador com registros de campanhas.
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        return ChatCampaign::query()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate();
    }

    /**
     * Criar uma nova campanha.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatCampaignDTO  $dto  Dados estruturados da campanha.
     * @return ChatCampaign Modelo criado.
     */
    public function create(string $tenantId, ChatCampaignDTO $dto): ChatCampaign
    {
        return ChatCampaign::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /**
     * Atualizar dados de uma campanha existente.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da campanha.
     * @param  ChatCampaignDTO  $dto  Novos dados estruturados.
     * @return ChatCampaign Modelo atualizado.
     */
    public function update(string $tenantId, string $id, ChatCampaignDTO $dto): ChatCampaign
    {
        $campaign = $this->find($tenantId, $id);
        $campaign->fill($dto->toArray());
        $campaign->save();

        return $campaign;
    }

    /**
     * Remover uma campanha do sistema.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da campanha.
     */
    public function delete(string $tenantId, string $id): void
    {
        $campaign = $this->find($tenantId, $id);
        $campaign->delete();
    }

    /**
     * Localizar uma campanha garantindo o isolamento do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da campanha.
     * @return ChatCampaign Modelo encontrado.
     */
    public function find(string $tenantId, string $id): ChatCampaign
    {
        return ChatCampaign::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * Resolver contatos com base nos critérios de filtro.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $criteria  Critérios de filtro (tags, status, company_id).
     * @return \Illuminate\Database\Eloquent\Collection Coleção de contatos.
     */
    public function resolveContacts(string $tenantId, array $criteria): \Illuminate\Database\Eloquent\Collection
    {
        $query = \Domain\CRM\Models\CRMContact::query()
            ->where('tenant_id', $tenantId);

        if (! empty($criteria['tags'])) {
            $query->whereHas('tags', function ($q) use ($criteria) {
                $q->whereIn('crm_tags.id', (array) $criteria['tags']);
            });
        }

        if (isset($criteria['status']) && $criteria['status'] !== '' && $criteria['status'] !== 'all') {
            $query->where('is_active', $criteria['status'] === 'active');
        }

        if (! empty($criteria['company_id'])) {
            $query->where('crm_company_id', $criteria['company_id']);
        }

        return $query->get();
    }

    /**
     * Contar contatos que atendem aos critérios.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function countAudience(string $tenantId, array $criteria): int
    {
        $query = \Domain\CRM\Models\CRMContact::query()
            ->where('tenant_id', $tenantId);

        if (! empty($criteria['tags'])) {
            $query->whereHas('tags', function ($q) use ($criteria) {
                $q->whereIn('crm_tags.id', (array) $criteria['tags']);
            });
        }

        if (isset($criteria['status']) && $criteria['status'] !== '' && $criteria['status'] !== 'all') {
            $query->where('is_active', $criteria['status'] === 'active');
        }

        if (! empty($criteria['company_id'])) {
            $query->where('crm_company_id', $criteria['company_id']);
        }

        return $query->count();
    }

    /**
     * Vincular contatos e iniciar o processamento da campanha.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da campanha.
     * @param  array<string, mixed>  $criteria  Critérios de filtro opcionais (se não salvos).
     * @return ChatCampaign Modelo com status atualizado.
     */
    public function send(string $tenantId, string $id, array $criteria = []): ChatCampaign
    {
        $campaign = $this->find($tenantId, $id);

        if (in_array($campaign->status, ['running', 'completed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Campaign cannot be sent in the current status.',
            ]);
        }

        if (! empty($criteria)) {
            $campaign->filter_criteria = $criteria;
            $campaign->save();
        }

        $contacts = $this->resolveContacts($tenantId, $campaign->filter_criteria ?? []);

        foreach ($contacts as $contact) {
            \Domain\Chat\Models\ChatCampaignContact::firstOrCreate([
                'tenant_id' => $tenantId,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
            ], [
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $campaign->status = 'running';
        $campaign->save();

        \Domain\Chat\Jobs\ProcessCampaignJob::dispatch($campaign);

        return $campaign;
    }

    /**
     * Gerar pré-visualização da mensagem com contatos reais.
     *
     * @return array<string, mixed>
     */
    public function preview(string $tenantId, string $message): array
    {
        // Encontrar um contato de exemplo (o primeiro ou aleatório)
        $sampleContact = \Domain\CRM\Models\CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $sampleContact) {
            return [
                'original' => $message,
                'preview' => $message,
                'vars_detected' => [],
                'sample_contact' => null,
                'warning' => 'Nenhum contato ativo encontrado para visualizar variáveis.',
            ];
        }

        $vars = [
            '{{name}}' => $sampleContact->name,
            '{{nome}}' => $sampleContact->name,
            '{{phone}}' => $sampleContact->phone,
            '{{email}}' => $sampleContact->email,
        ];

        $preview = str_replace(array_keys($vars), array_values($vars), $message);

        // Detectar quais variáveis foram usadas
        $detected = [];
        foreach (array_keys($vars) as $key) {
            if (str_contains($message, $key)) {
                $detected[] = $key;
            }
        }

        return [
            'original' => $message,
            'preview' => $preview,
            'vars_detected' => $detected,
            'sample_contact' => [
                'name' => $sampleContact->name,
                'phone' => $sampleContact->phone,
            ],
        ];
    }
}
