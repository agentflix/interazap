<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMNegotiationProductActions;
use Domain\CRM\DTOs\CRMNegotiationProductDTO;
use Domain\CRM\Http\Requests\CRMNegotiationProductRequest;
use Domain\CRM\Http\Requests\CRMNegotiationProductUpdateRequest;
use Domain\CRM\Http\Resources\CRMNegotiationProductResource;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para itens (produtos/serviços) de negociações do CRM.
 *
 * Gerencia adição, listagem, atualização e remoção de itens vinculados a negociações. Requer autenticação Sanctum.
 */
final class CRMNegotiationProductController extends BaseController
{
    /**
     * @param  CRMNegotiationProductActions  $actions  Ação de gestão de itens de negociação.
     */
    public function __construct(private readonly CRMNegotiationProductActions $actions) {}

    /**
     * Lista itens de uma negociação.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Lista de itens da negociação.
     */
    public function index(Request $request, string $negotiationId): JsonResponse
    {
        $negotiation = CRMNegotiation::query()->findOrFail($negotiationId);
        $this->authorize('view', $negotiation);

        $items = $this->actions->list($negotiation->tenant_id, $negotiationId)
            ->map(fn ($item) => new CRMNegotiationProductResource($item));

        return $this->success($items, 'Itens da negociação listados');
    }

    /**
     * Adiciona item a uma negociação.
     *
     * @param  CRMNegotiationProductRequest  $request  Dados da requisição com nome, quantidade e preço.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Item adicionado.
     */
    public function store(CRMNegotiationProductRequest $request, string $negotiationId): JsonResponse
    {
        $negotiation = CRMNegotiation::query()->findOrFail($negotiationId);
        $this->authorize('update', $negotiation);

        $item = $this->actions->add($negotiation->tenant_id, CRMNegotiationProductDTO::fromRequest($request, $negotiationId));

        return $this->created(new CRMNegotiationProductResource($item), 'Item adicionado à negociação');
    }

    /**
     * Atualiza item de negociação.
     *
     * @param  CRMNegotiationProductUpdateRequest  $request  Dados atualizados do item.
     * @param  string  $id  ID do item.
     * @return JsonResponse Item atualizado.
     */
    public function update(CRMNegotiationProductUpdateRequest $request, string $id): JsonResponse
    {
        $item = CRMNegotiationProduct::query()
            ->with('negotiation')
            ->findOrFail($id);
        $negotiation = $item->negotiation;

        if (! $negotiation instanceof CRMNegotiation) {
            abort(404, 'Negotiation not found');
        }

        $this->authorize('update', $negotiation);

        $item = $this->actions->update($negotiation->tenant_id, $id, $request->validated());

        return $this->success(new CRMNegotiationProductResource($item), 'Item atualizado');
    }

    /**
     * Remove item de negociação.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $id  ID do item.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $item = CRMNegotiationProduct::query()
            ->with('negotiation')
            ->findOrFail($id);
        $negotiation = $item->negotiation;

        if (! $negotiation instanceof CRMNegotiation) {
            abort(404, 'Negotiation not found');
        }

        $this->authorize('update', $negotiation);

        $this->actions->delete($negotiation->tenant_id, $id);

        return $this->noContent();
    }
}
