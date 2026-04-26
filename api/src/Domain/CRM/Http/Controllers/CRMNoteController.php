<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMNoteActions;
use Domain\CRM\DTOs\CRMNoteDTO;
use Domain\CRM\Http\Requests\CRMNoteRequest;
use Domain\CRM\Http\Resources\CRMNoteResource;
use Domain\CRM\Models\CRMNote;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para notas de entidades do CRM.
 *
 * @see CRMNoteActions
 */
final class CRMNoteController extends BaseController
{
    /**
     * @param  CRMNoteActions  $actions  Ação de gestão de notas.
     */
    public function __construct(private readonly CRMNoteActions $actions) {}

    /**
     * Listar notas de um contato.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $contactId  ID do contato.
     * @return JsonResponse Lista paginada de notas.
     */
    public function listForContact(Request $request, string $contactId): JsonResponse
    {
        $this->authorize('viewAny', CRMNote::class);

        $tenantId = $this->tenantId();
        $paginator = $this->actions->list($tenantId, \Domain\CRM\Models\CRMContact::class, $contactId);
        $paginator->getCollection()->transform(fn ($item) => new CRMNoteResource($item));

        return $this->paginated($paginator, 'Notas do contato');
    }

    /**
     * Criar nota para um contato.
     *
     * @param  CRMNoteRequest  $request  Requisição com dados da nota.
     * @param  string  $contactId  ID do contato.
     * @return JsonResponse Nota criada.
     */
    public function storeForContact(CRMNoteRequest $request, string $contactId): JsonResponse
    {
        $this->authorize('create', CRMNote::class);

        $tenantId = $this->tenantId();
        $userId = (string) $request->user()?->id;
        $note = $this->actions->create(
            $tenantId,
            $userId,
            CRMNoteDTO::fromRequest($request, \Domain\CRM\Models\CRMContact::class, $contactId)
        );

        return $this->created(new CRMNoteResource($note), 'Nota criada');
    }

    /**
     * Listar notas de uma negociação.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Lista paginada de notas.
     */
    public function listForNegotiation(Request $request, string $negotiationId): JsonResponse
    {
        $this->authorize('viewAny', CRMNote::class);

        $tenantId = $this->tenantId();
        $paginator = $this->actions->list($tenantId, \Domain\CRM\Models\CRMNegotiation::class, $negotiationId);
        $paginator->getCollection()->transform(fn ($item) => new CRMNoteResource($item));

        return $this->paginated($paginator, 'Notas da negociação');
    }

    /**
     * Criar nota para uma negociação.
     *
     * @param  CRMNoteRequest  $request  Requisição com dados da nota.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Nota criada.
     */
    public function storeForNegotiation(CRMNoteRequest $request, string $negotiationId): JsonResponse
    {
        $this->authorize('create', CRMNote::class);

        $tenantId = $this->tenantId();
        $userId = (string) $request->user()?->id;
        $note = $this->actions->create(
            $tenantId,
            $userId,
            CRMNoteDTO::fromRequest($request, \Domain\CRM\Models\CRMNegotiation::class, $negotiationId)
        );

        return $this->created(new CRMNoteResource($note), 'Nota criada');
    }
}
