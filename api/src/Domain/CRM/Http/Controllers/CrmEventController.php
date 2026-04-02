<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Carbon\CarbonImmutable;
use Domain\CRM\Actions\CRMEventActions;
use Domain\CRM\DTOs\CRMEventDTO;
use Domain\CRM\Http\Requests\CRMEventStoreRequest;
use Domain\CRM\Http\Requests\CRMEventUpdateRequest;
use Domain\CRM\Http\Resources\CRMEventResource;
use Domain\CRM\Models\CRMEvent;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestão de eventos da agenda CRM.
 *
 * @see CRMEventActions
 */
final class CRMEventController extends BaseController
{
    /**
     * @param  CRMEventActions  $actions  Ação de gestão de eventos.
     */
    public function __construct(private readonly CRMEventActions $actions) {}

    /**
     * Listar eventos com filtros.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de eventos.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMEvent::class);

        $tenantId = $this->tenantId();
        $perPage = (int) $request->input('per_page', 15);

        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'uuid', 'exists:auth_users,id'],
            'status' => ['nullable', 'in:'.implode(',', CRMEvent::statuses())],
            'type' => ['nullable', 'in:'.implode(',', CRMEvent::types())],
            'participant_id' => ['nullable', 'uuid', 'exists:auth_users,id'],
            'linkable_type' => ['nullable', 'string'],
            'linkable_id' => ['nullable', 'uuid'],
            'is_all_day' => ['nullable', 'boolean'],
            'recurrence' => ['nullable', 'in:'.implode(',', CRMEvent::recurrences())],
            'location' => ['nullable', 'string'],
            'has_reminders' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $filters = collect($validated)
            ->only([
                'search',
                'start_date',
                'end_date',
                'user_id',
                'status',
                'type',
                'participant_id',
                'linkable_type',
                'linkable_id',
                'is_all_day',
                'recurrence',
                'location',
                'has_reminders',
                'sort_by',
                'sort_dir',
            ])
            ->toArray();

        $paginator = $this->actions->list($tenantId, $filters, $perPage);
        $paginator->getCollection()->transform(fn ($item) => new CRMEventResource($item));

        return $this->paginated($paginator, 'Eventos listados');
    }

    /**
     * Criar novo evento.
     *
     * @param  CRMEventStoreRequest  $request  Requisição com dados do evento.
     * @return JsonResponse Evento criado.
     */
    public function store(CRMEventStoreRequest $request): JsonResponse
    {
        $this->authorize('create', CRMEvent::class);

        $event = $this->actions->create(CRMEventDTO::fromRequest($request));

        return $this->created(new CRMEventResource($event), 'Evento criado com sucesso');
    }

    /**
     * Obter detalhes de um evento.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do evento.
     * @return JsonResponse Dados do evento.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $event = $this->actions->find($tenantId, $id);
        $this->authorize('view', $event);

        return $this->success(new CRMEventResource($event), 'Evento carregado');
    }

    /**
     * Atualizar evento.
     *
     * @param  CRMEventUpdateRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do evento.
     * @return JsonResponse Evento atualizado.
     */
    public function update(CRMEventUpdateRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $event = $this->actions->find($tenantId, $id);
        $this->authorize('update', $event);

        $event = $this->actions->update($tenantId, $id, CRMEventDTO::fromRequest($request));

        return $this->success(new CRMEventResource($event), 'Evento atualizado');
    }

    /**
     * Excluir evento.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do evento.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $event = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $event);

        $this->actions->delete($tenantId, $id);

        return $this->noContent();
    }

    /**
     * Atualizar status do evento.
     *
     * @param  Request  $request  Requisição com novo status.
     * @param  string  $id  ID do evento.
     * @return JsonResponse Evento com status atualizado.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $event = $this->actions->find($tenantId, $id);
        $this->authorize('update', $event);

        $request->validate([
            'status' => ['required', 'in:'.implode(',', CRMEvent::statuses())],
        ]);

        $event = $this->actions->updateStatus($tenantId, $id, (string) $request->input('status'));

        return $this->success(new CRMEventResource($event), 'Status do evento atualizado');
    }

    /**
     * Obter eventos para visualização em calendário.
     *
     * @param  Request  $request  Requisição com período.
     * @return JsonResponse Lista de eventos para calendário.
     */
    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMEvent::class);

        $tenantId = $this->tenantId();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'user_id' => ['nullable', 'uuid', 'exists:auth_users,id'],
        ]);

        $events = $this->actions->calendar(
            $tenantId,
            CarbonImmutable::parse($validated['start_date']),
            CarbonImmutable::parse($validated['end_date']),
            $validated['user_id'] ?? null
        )->map(fn ($event) => new CRMEventResource($event));

        return $this->success($events, 'Eventos para calendário');
    }

    /**
     * Obter próximos eventos do usuário.
     *
     * @param  Request  $request  Requisição com limite.
     * @return JsonResponse Lista de próximos eventos.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMEvent::class);

        $tenantId = $this->tenantId();
        $userId = (string) $request->user()?->id;
        $limit = (int) $request->input('limit', 10);

        $events = $this->actions
            ->upcoming($tenantId, $userId, $limit)
            ->map(fn ($event) => new CRMEventResource($event));

        return $this->success($events, 'Próximos eventos');
    }

    /**
     * Obter eventos vinculados a uma entidade.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $type  Tipo da entidade.
     * @param  string  $id  ID da entidade.
     * @return JsonResponse Lista de eventos vinculados.
     */
    public function linked(Request $request, string $type, string $id): JsonResponse
    {
        $this->authorize('viewAny', CRMEvent::class);

        $tenantId = $this->tenantId();

        $events = $this->actions
            ->linked($tenantId, $type, $id)
            ->map(fn ($event) => new CRMEventResource($event));

        return $this->success($events, 'Eventos vinculados');
    }

    /**
     * Obter estatísticas de eventos.
     *
     * @param  Request  $request  Requisição com período.
     * @return JsonResponse Estatísticas da agenda.
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMEvent::class);

        $tenantId = $this->tenantId();

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $stats = $this->actions->statistics(
            $tenantId,
            $startDate ? CarbonImmutable::parse($startDate) : null,
            $endDate ? CarbonImmutable::parse($endDate) : null,
        );

        return $this->success($stats, 'Estatísticas da agenda');
    }
}
