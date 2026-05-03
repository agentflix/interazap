<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Http\Requests\ChatRoutingQueueRequest;
use Domain\Chat\Http\Resources\ChatRoutingQueueResource;
use Domain\Chat\Models\ChatRoutingAgentSkill;
use Domain\Chat\Models\ChatRoutingQueue;
use Domain\Chat\Models\ChatRoutingQueueAgent;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de Filas de Roteamento de Atendimentos.
 *
 * Gerencia a configuração de distribuição automática de tickets entre agentes,
 * incluindo filas por canal (instância) e fila global, além do controle de agentes.
 *
 * @category Controllers
 */
final class ChatRoutingQueueController extends BaseController
{
    /**
     * Exibir a fila de roteamento de um canal específico.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Fila de roteamento ou 404.
     */
    public function showChannel(Request $request, string $id): JsonResponse
    {
        $queue = ChatRoutingQueue::with(['agents.skills'])->forInstance($id)->first();

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('view', $queue);

        return $this->success(new ChatRoutingQueueResource($queue));
    }

    /**
     * Criar a fila de roteamento para um canal específico.
     *
     * @param  ChatRoutingQueueRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Fila criada (201).
     */
    public function storeChannel(ChatRoutingQueueRequest $request, string $id): JsonResponse
    {
        $this->authorize('manage', ChatRoutingQueue::class);

        $queue = ChatRoutingQueue::create([
            'tenant_id' => $this->tenantId($request),
            'instance_id' => $id,
            'name' => $request->input('name'),
            'strategy' => $request->input('strategy'),
            'is_enabled' => $request->boolean('is_enabled'),
            'max_open_tickets_per_agent' => $request->input('max_open_tickets_per_agent'),
        ]);

        return $this->created(new ChatRoutingQueueResource($queue));
    }

    /**
     * Atualizar a fila de roteamento de um canal específico.
     *
     * @param  ChatRoutingQueueRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Fila atualizada.
     */
    public function updateChannel(ChatRoutingQueueRequest $request, string $id): JsonResponse
    {
        $queue = ChatRoutingQueue::with(['agents.skills'])->forInstance($id)->first();

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('manage', $queue);

        $queue->update([
            'name' => $request->input('name', $queue->name),
            'strategy' => $request->input('strategy', $queue->strategy),
            'is_enabled' => $request->boolean('is_enabled', $queue->is_enabled),
            'max_open_tickets_per_agent' => $request->input('max_open_tickets_per_agent', $queue->max_open_tickets_per_agent),
        ]);

        return $this->success(new ChatRoutingQueueResource($queue));
    }

    /**
     * Exibir a fila de roteamento global.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Fila global ou 404.
     */
    public function showGlobal(Request $request): JsonResponse
    {
        $queue = ChatRoutingQueue::with(['agents.skills'])->global()->first();

        if ($queue === null) {
            return $this->notFound('Fila de roteamento global não encontrada');
        }

        $this->authorize('view', $queue);

        return $this->success(new ChatRoutingQueueResource($queue));
    }

    /**
     * Criar a fila de roteamento global.
     *
     * @param  ChatRoutingQueueRequest  $request  Dados validados.
     * @return JsonResponse Fila global criada (201).
     */
    public function storeGlobal(ChatRoutingQueueRequest $request): JsonResponse
    {
        $this->authorize('manage', ChatRoutingQueue::class);

        $queue = ChatRoutingQueue::create([
            'tenant_id' => $this->tenantId($request),
            'instance_id' => null,
            'name' => $request->input('name'),
            'strategy' => $request->input('strategy'),
            'is_enabled' => $request->boolean('is_enabled'),
            'max_open_tickets_per_agent' => $request->input('max_open_tickets_per_agent'),
        ]);

        return $this->created(new ChatRoutingQueueResource($queue));
    }

    /**
     * Atualizar a fila de roteamento global.
     *
     * @param  ChatRoutingQueueRequest  $request  Dados validados.
     * @return JsonResponse Fila global atualizada.
     */
    public function updateGlobal(ChatRoutingQueueRequest $request): JsonResponse
    {
        $queue = ChatRoutingQueue::with(['agents.skills'])->global()->first();

        if ($queue === null) {
            return $this->notFound('Fila de roteamento global não encontrada');
        }

        $this->authorize('manage', $queue);

        $queue->update([
            'name' => $request->input('name', $queue->name),
            'strategy' => $request->input('strategy', $queue->strategy),
            'is_enabled' => $request->boolean('is_enabled', $queue->is_enabled),
            'max_open_tickets_per_agent' => $request->input('max_open_tickets_per_agent', $queue->max_open_tickets_per_agent),
        ]);

        return $this->success(new ChatRoutingQueueResource($queue));
    }

    /**
     * Listar os agentes de uma fila de roteamento.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $scope  Escopo: 'channel' ou 'global'.
     * @param  string|null  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Lista de agentes ordenada por posição.
     */
    public function indexAgents(Request $request, string $scope, ?string $id = null): JsonResponse
    {
        $queue = $this->resolveQueue($scope, $id);

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('view', $queue);

        $agents = $queue->agents()->orderBy('position')->get();

        return $this->success($this->mapAgents($agents));
    }

    /**
     * Adicionar um agente à fila de roteamento.
     *
     * @param  ChatRoutingQueueRequest  $request  Dados validados (user_id, position).
     * @param  string  $scope  Escopo: 'channel' ou 'global'.
     * @param  string|null  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Agente adicionado (201).
     */
    public function storeAgent(ChatRoutingQueueRequest $request, string $scope, ?string $id = null): JsonResponse
    {
        $queue = $this->resolveQueue($scope, $id);

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('addAgent', [$queue, $request->input('user_id')]);

        $agent = $queue->agents()->create([
            'user_id' => $request->input('user_id'),
            'position' => $request->input('position', 0),
            'is_active' => true,
        ]);

        return $this->created($this->mapAgent($agent));
    }

    /**
     * Remover um agente da fila de roteamento.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $scope  Escopo: 'channel' ou 'global'.
     * @param  string  $userId  Identificador UUID do usuário (agente).
     * @param  string|null  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Confirmação de remoção (204).
     */
    public function destroyAgent(Request $request, string $scope, string $userId, ?string $id = null): JsonResponse
    {
        $queue = $this->resolveQueue($scope, $id);

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('manage', $queue);

        $queue->agents()->where('user_id', $userId)->delete();

        return $this->noContent();
    }

    /**
     * Reordenar os agentes da fila de roteamento.
     *
     * @param  ChatRoutingQueueRequest  $request  Dados validados (agents array).
     * @param  string  $scope  Escopo: 'channel' ou 'global'.
     * @param  string|null  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Lista de agentes atualizada.
     */
    public function reorderAgents(ChatRoutingQueueRequest $request, string $scope, ?string $id = null): JsonResponse
    {
        $queue = $this->resolveQueue($scope, $id);

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('manage', $queue);

        $agentsData = $request->input('agents', []);

        DB::transaction(function () use ($queue, $agentsData): void {
            foreach ($agentsData as $data) {
                $queue->agents()
                    ->where('user_id', $data['user_id'])
                    ->update(['position' => $data['position']]);
            }
        });

        $agents = $queue->agents()->orderBy('position')->get();

        return $this->success($this->mapAgents($agents));
    }

    /**
     * Listar as skills de um agente na fila.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $scope  Escopo: 'channel' ou 'global'.
     * @param  string  $userId  Identificador UUID do agente.
     * @param  string|null  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Lista de skills.
     */
    public function indexSkills(Request $request, string $scope, string $userId, ?string $id = null): JsonResponse
    {
        $queue = $this->resolveQueue($scope, $id);

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('view', $queue);

        $skills = ChatRoutingAgentSkill::query()
            ->where('queue_id', $queue->id)
            ->where('user_id', $userId)
            ->get();

        return $this->success($skills->map(fn (ChatRoutingAgentSkill $skill): array => [
            'id' => $skill->id,
            'skill' => $skill->skill,
        ]));
    }

    /**
     * Adicionar uma skill a um agente na fila.
     *
     * @param  ChatRoutingQueueRequest  $request  Dados validados.
     * @param  string  $scope  Escopo: 'channel' ou 'global'.
     * @param  string  $userId  Identificador UUID do agente.
     * @param  string|null  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Skill criada (201).
     */
    public function storeSkill(ChatRoutingQueueRequest $request, string $scope, string $userId, ?string $id = null): JsonResponse
    {
        $queue = $this->resolveQueue($scope, $id);

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('manage', $queue);

        $skillValue = $request->input('skill');

        $existing = ChatRoutingAgentSkill::query()
            ->where('queue_id', $queue->id)
            ->where('user_id', $userId)
            ->where('skill', $skillValue)
            ->first();

        if ($existing !== null) {
            return $this->success(['id' => $existing->id, 'skill' => $existing->skill]);
        }

        $skill = ChatRoutingAgentSkill::create([
            'queue_id' => $queue->id,
            'user_id' => $userId,
            'skill' => $skillValue,
        ]);

        return $this->created(['id' => $skill->id, 'skill' => $skill->skill]);
    }

    /**
     * Remover uma skill de um agente na fila.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $scope  Escopo: 'channel' ou 'global'.
     * @param  string  $userId  Identificador UUID do agente.
     * @param  string  $skill  Skill a remover.
     * @param  string|null  $id  Identificador UUID da instância (canal).
     * @return JsonResponse Confirmação de remoção (204).
     */
    public function destroySkill(Request $request, string $scope, string $userId, string $skill, ?string $id = null): JsonResponse
    {
        $queue = $this->resolveQueue($scope, $id);

        if ($queue === null) {
            return $this->notFound('Fila de roteamento não encontrada');
        }

        $this->authorize('manage', $queue);

        ChatRoutingAgentSkill::query()
            ->where('queue_id', $queue->id)
            ->where('user_id', $userId)
            ->where('skill', $skill)
            ->delete();

        return $this->noContent();
    }

    /**
     * Resolver a fila de roteamento com base no escopo e ID de instância.
     *
     * @param  string  $scope  'channel' ou 'global'.
     * @param  string|null  $id  Identificador UUID da instância.
     * @return ChatRoutingQueue|null Fila encontrada ou null.
     */
    private function resolveQueue(string $scope, ?string $id): ?ChatRoutingQueue
    {
        if ($scope === 'channel') {
            return $id !== null
                ? ChatRoutingQueue::forInstance($id)->first()
                : null;
        }

        return ChatRoutingQueue::global()->first();
    }

    /**
     * Mapear uma coleção de agentes para array padronizado.
     *
     * @param  Collection<int, ChatRoutingQueueAgent>  $agents
     * @return array<int, array<string, mixed>>
     */
    private function mapAgents(Collection $agents): array
    {
        return $agents->map(fn (ChatRoutingQueueAgent $agent): array => $this->mapAgent($agent))->all();
    }

    /**
     * Mapear um único agente para array padronizado.
     *
     * @return array<string, mixed>
     */
    private function mapAgent(ChatRoutingQueueAgent $agent): array
    {
        return [
            'id' => $agent->id,
            'user_id' => $agent->user_id,
            'position' => $agent->position,
            'last_assigned_at' => $agent->last_assigned_at?->format(DATE_ATOM),
            'is_active' => $agent->is_active,
            'skills' => $agent->relationLoaded('skills')
                ? $agent->skills->pluck('skill')->all()
                : [],
        ];
    }
}
