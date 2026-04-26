<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMContactActions;
use Domain\CRM\DTOs\CRMContactDTO;
use Domain\CRM\DTOs\CRMContactPhoneDTO;
use Domain\CRM\Http\Requests\CRMContactPatchRequest;
use Domain\CRM\Http\Requests\CRMContactPhoneRequest;
use Domain\CRM\Http\Requests\CRMContactRequest;
use Domain\CRM\Http\Resources\CRMContactResource;
use Domain\CRM\Models\CRMContact;
use Domain\Shared\Contracts\ActivityBroadcastService;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Http\Controllers\Concerns\HandlesCrudOperations;
use Domain\Shared\Support\ListFilterNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestão de contatos CRM.
 *
 * @see CRMContactActions
 */
final class CRMContactController extends BaseController
{
    use HandlesCrudOperations;

    /**
     * @param  CRMContactActions  $actions  Ação de gestão de contatos.
     * @param  ActivityBroadcastService  $activityBroadcast  Serviço de broadcast.
     */
    public function __construct(
        private readonly CRMContactActions $actions,
        private readonly ActivityBroadcastService $activityBroadcast
    ) {}

    /**
     * Listar contatos com filtros.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de contatos.
     */
    public function index(Request $request): JsonResponse
    {
        $isActive = ListFilterNormalizer::normalizeIsActive($request->input('is_active'), true);

        return $this->crudIndex(
            $request,
            CRMContact::class,
            fn (string $tenantId) => $this->actions->list($tenantId, [
                'search' => $request->input('search'),
                'is_active' => $isActive,
                'per_page' => $request->input('per_page'),
                'sort_by' => $request->input('sort_by'),
                'sort_dir' => $request->input('sort_dir'),
            ]),
            fn (mixed $item) => new CRMContactResource($item),
            'Contatos listados',
        );
    }

    /**
     * Criar novo contato.
     *
     * @param  CRMContactRequest  $request  Requisição com dados do contato.
     * @return JsonResponse Contato criado.
     */
    public function store(CRMContactRequest $request): JsonResponse
    {
        return $this->crudStore(
            $request,
            CRMContact::class,
            fn (string $tenantId) => $this->actions->create($tenantId, CRMContactDTO::fromRequest($request)),
            fn (mixed $item) => new CRMContactResource($item),
            'Contato criado',
        );
    }

    /**
     * Obter detalhes de um contato.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do contato.
     * @return JsonResponse Dados do contato.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->crudShow(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (mixed $item) => new CRMContactResource($item),
            'Contato carregado',
        );
    }

    /**
     * Atualizar contato (full update).
     *
     * @param  CRMContactRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do contato.
     * @return JsonResponse Contato atualizado.
     */
    public function update(CRMContactRequest $request, string $id): JsonResponse
    {
        return $this->crudUpdate(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->update($tenantId, $modelId, CRMContactDTO::fromRequest($request)),
            fn (mixed $item) => new CRMContactResource($item),
            'Contato atualizado',
        );
    }

    /**
     * Atualizar parcialmente um contato (patch).
     *
     * @param  CRMContactPatchRequest  $request  Requisição com dados parciais.
     * @param  string  $id  ID do contato.
     * @return JsonResponse Contato atualizado.
     */
    public function patch(CRMContactPatchRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $contact = $this->actions->find($tenantId, $id);
        $this->authorize('update', $contact);

        $data = $request->validated();
        $ticketId = $data['ticket_id'] ?? null;
        unset($data['ticket_id']);

        $contact = $this->actions->updatePartial($tenantId, $id, $data);
        $resource = new CRMContactResource($contact);

        if ($ticketId) {
            $this->activityBroadcast->emitContactUpdated(
                (string) $ticketId,
                (string) $contact->id,
                $resource->toArray($request)
            );
        }

        return $this->success($resource, 'Contato atualizado');
    }

    /**
     * Excluir contato.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do contato.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->crudDestroy(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->delete($tenantId, $modelId),
        );
    }

    /**
     * Restaurar contato excluído.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do contato.
     * @return JsonResponse Contato restaurado.
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $contact = $this->actions->restore($tenantId, $id);
        $this->authorize('update', $contact);

        return $this->success(new CRMContactResource($contact), 'Contato restaurado');
    }

    /**
     * Adicionar telefone a um contato.
     *
     * @param  CRMContactPhoneRequest  $request  Requisição com dados do telefone.
     * @param  string  $id  ID do contato.
     * @return JsonResponse Telefone adicionado.
     */
    public function addPhone(CRMContactPhoneRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $contact = $this->actions->find($tenantId, $id);
        $this->authorize('update', $contact);

        $phone = $this->actions->createPhone(
            $tenantId,
            CRMContactPhoneDTO::fromRequest($request, $id),
            (bool) $request->validated('force_reassign')
        );

        return $this->created($phone->toArray(), 'Telefone adicionado');
    }
}
