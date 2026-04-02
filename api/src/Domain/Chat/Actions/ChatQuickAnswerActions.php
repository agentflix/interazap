<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatQuickAnswerDTO;
use Domain\Chat\Models\ChatQuickAnswer;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Casos de Uso para Respostas Rápidas.
 *
 * Centraliza a lógica de CRUD e listagem de atalhos textuais utilizados para
 * acelerar o atendimento humano, incluindo suporte a categorias e filtros de busca.
 *
 * @category Actions
 */
final class ChatQuickAnswerActions
{
    /**
     * Listar respostas rápidas com paginação e filtros.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $filters  Filtros (search, category, is_active).
     * @return LengthAwarePaginator Paginador com registros.
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = ChatQuickAnswer::query()
            ->select([
                'id',
                'tenant_id',
                'name',
                'shortcut',
                'content',
                'category',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where('tenant_id', $tenantId);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('shortcut', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->latest()->paginate();
    }

    /**
     * Listar todas as respostas rápidas ativas do tenant (uso em seletores).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return \Illuminate\Support\Collection<int, ChatQuickAnswer> Coleção completa.
     */
    public function listAllActive(string $tenantId)
    {
        return ChatQuickAnswer::query()
            ->select(['id', 'tenant_id', 'name', 'shortcut', 'content', 'category', 'is_active'])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Criar uma nova resposta rápida.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatQuickAnswerDTO  $dto  Dados estruturados.
     * @return ChatQuickAnswer Modelo criado.
     */
    public function create(string $tenantId, ChatQuickAnswerDTO $dto): ChatQuickAnswer
    {
        return ChatQuickAnswer::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /**
     * Atualizar uma resposta rápida existente.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da resposta.
     * @param  ChatQuickAnswerDTO  $dto  Novos dados.
     * @return ChatQuickAnswer Modelo atualizado.
     */
    public function update(string $tenantId, string $id, ChatQuickAnswerDTO $dto): ChatQuickAnswer
    {
        $qa = $this->find($tenantId, $id);
        $qa->fill($dto->toArray());
        $qa->save();

        return $qa;
    }

    /**
     * Remover uma resposta rápida do sistema.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da resposta.
     */
    public function delete(string $tenantId, string $id): void
    {
        $qa = $this->find($tenantId, $id);
        $qa->delete();
    }

    /**
     * Localizar uma resposta rápida garantindo isolamento de tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da resposta.
     * @return ChatQuickAnswer Modelo encontrado.
     */
    public function find(string $tenantId, string $id): ChatQuickAnswer
    {
        return ChatQuickAnswer::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }
}
