<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatTransmissionListDTO;
use Domain\Chat\Models\ChatTransmissionList;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Casos de Uso para Listas de Transmissão de Chat.
 *
 * @category Actions
 */
final class ChatTransmissionListActions
{
    /**
     * Listar todas as listas de transmissão do tenant com paginação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return LengthAwarePaginator Paginador com registros de listas.
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        return ChatTransmissionList::query()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate();
    }

    /**
     * Criar uma nova lista de transmissão.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatTransmissionListDTO  $dto  Dados estruturados da lista.
     * @return ChatTransmissionList Modelo criado.
     */
    public function create(string $tenantId, ChatTransmissionListDTO $dto): ChatTransmissionList
    {
        return ChatTransmissionList::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /**
     * Atualizar dados de uma lista de transmissão existente.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da lista.
     * @param  ChatTransmissionListDTO  $dto  Novos dados estruturados.
     * @return ChatTransmissionList Modelo atualizado.
     */
    public function update(string $tenantId, string $id, ChatTransmissionListDTO $dto): ChatTransmissionList
    {
        $transmissionList = $this->find($tenantId, $id);
        $transmissionList->fill($dto->toArray());
        $transmissionList->save();

        return $transmissionList;
    }

    /**
     * Remover uma lista de transmissão do sistema.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da lista.
     */
    public function delete(string $tenantId, string $id): void
    {
        $transmissionList = $this->find($tenantId, $id);
        $transmissionList->delete();
    }

    /**
     * Localizar uma lista de transmissão garantindo o isolamento do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da lista.
     * @return ChatTransmissionList Modelo encontrado.
     */
    public function find(string $tenantId, string $id): ChatTransmissionList
    {
        return ChatTransmissionList::query()
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
     * Vincular contatos e iniciar o processamento da lista de transmissão.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da lista.
     * @param  array<string, mixed>  $criteria  Critérios de filtro opcionais (se não salvos).
     * @return ChatTransmissionList Modelo com status atualizado.
     */
    public function send(string $tenantId, string $id, array $criteria = []): ChatTransmissionList
    {
        $transmissionList = $this->find($tenantId, $id);

        if (in_array($transmissionList->status, ['running', 'completed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Transmission list cannot be sent in the current status.',
            ]);
        }

        if (! empty($criteria)) {
            $transmissionList->filter_criteria = $criteria;
            $transmissionList->save();
        }

        $contacts = $this->resolveContacts($tenantId, $transmissionList->filter_criteria ?? []);

        foreach ($contacts as $contact) {
            \Domain\Chat\Models\ChatTransmissionListContact::firstOrCreate([
                'tenant_id' => $tenantId,
                'transmission_list_id' => $transmissionList->id,
                'contact_id' => $contact->id,
            ], [
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $transmissionList->status = 'running';
        $transmissionList->save();

        \Domain\Chat\Jobs\ProcessTransmissionListJob::dispatch($transmissionList);

        return $transmissionList;
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
