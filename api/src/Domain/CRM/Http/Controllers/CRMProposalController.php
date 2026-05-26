<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMProposalActions;
use Domain\CRM\DTOs\CRMProposalDTO;
use Domain\CRM\Http\Requests\CRMProposalPublicRequest;
use Domain\CRM\Http\Requests\CRMProposalRequest;
use Domain\CRM\Http\Resources\CRMProposalResource;
use Domain\CRM\Models\CRMProposal;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para propostas comerciais de negociações do CRM.
 *
 * Gerencia criação, listagem, atualização, envio, duplicação e aceitação/rejeição pública de propostas. Requer autenticação Sanctum.
 */
final class CRMProposalController extends BaseController
{
    /**
     * @param  CRMProposalActions  $actions  Ação de gestão de propostas.
     */
    public function __construct(private readonly CRMProposalActions $actions) {}

    /**
     * Lista propostas de uma negociação.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Lista paginada de propostas.
     */
    public function index(Request $request, string $negotiationId): JsonResponse
    {
        $this->authorize('viewAny', CRMProposal::class);

        $tenantId = $this->tenantId();
        $paginator = $this->actions->listByNegotiation($tenantId, $negotiationId);
        $paginator->getCollection()->transform(fn ($item) => new CRMProposalResource($item));

        return $this->paginated($paginator, 'Propostas listadas');
    }

    /**
     * Cria nova proposta para uma negociação.
     *
     * @param  CRMProposalRequest  $request  Dados da requisição com título, itens e validade.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Proposta criada.
     */
    public function store(CRMProposalRequest $request, string $negotiationId): JsonResponse
    {
        $this->authorize('create', CRMProposal::class);

        $tenantId = $this->tenantId();
        $proposal = $this->actions->create($tenantId, CRMProposalDTO::fromRequest($request, $negotiationId));

        return $this->created(new CRMProposalResource($proposal), 'Proposta criada');
    }

    /**
     * Obtém detalhes de uma proposta.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $id  ID da proposta.
     * @return JsonResponse Dados da proposta.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $proposal = $this->actions->find($tenantId, $id);
        $this->authorize('view', $proposal);

        return $this->success(new CRMProposalResource($proposal), 'Proposta carregada');
    }

    /**
     * Atualiza proposta.
     *
     * @param  CRMProposalRequest  $request  Dados atualizados da proposta.
     * @param  string  $id  ID da proposta.
     * @return JsonResponse Proposta atualizada.
     */
    public function update(CRMProposalRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $current = $this->actions->find($tenantId, $id);
        $this->authorize('update', $current);

        $currentStatus = $current->status->value;
        $proposal = $this->actions->update(
            $tenantId,
            $id,
            CRMProposalDTO::fromRequest($request, $current->crm_negotiation_id, $currentStatus)
        );

        return $this->success(new CRMProposalResource($proposal), 'Proposta atualizada');
    }

    /**
     * Remove proposta.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $id  ID da proposta.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $proposal = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $proposal);

        $this->actions->delete($tenantId, $id);

        return $this->noContent();
    }

    /**
     * Envia proposta ao cliente.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $id  ID da proposta.
     * @return JsonResponse Proposta com status atualizado para enviada.
     */
    public function send(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $proposal = $this->actions->find($tenantId, $id);
        $this->authorize('update', $proposal);

        $proposal = $this->actions->send($tenantId, $id);

        return $this->success(new CRMProposalResource($proposal), 'Proposta enviada');
    }

    /**
     * Duplica proposta existente.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $id  ID da proposta a ser duplicada.
     * @return JsonResponse Nova proposta criada como cópia.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $this->actions->find($tenantId, $id);
        $this->authorize('create', CRMProposal::class);

        $proposal = $this->actions->duplicate($tenantId, $id);

        return $this->created(new CRMProposalResource($proposal), 'Proposta duplicada');
    }

    /**
     * Exibe proposta publicamente via token (sem autenticação).
     *
     * @param  string  $token  Token público da proposta.
     * @return JsonResponse Dados da proposta e marca como visualizada.
     */
    public function publicView(string $token): JsonResponse
    {
        $proposal = $this->actions->findByToken($token);
        $proposal = $this->actions->markViewed($proposal);

        return $this->success(new CRMProposalResource($proposal), 'Proposta carregada');
    }

    /**
     * Aceita proposta publicamente via token (sem autenticação).
     *
     * @param  CRMProposalPublicRequest  $request  Dados da requisição pública.
     * @param  string  $token  Token público da proposta.
     * @return JsonResponse Proposta com status aceito.
     */
    public function publicAccept(CRMProposalPublicRequest $request, string $token): JsonResponse
    {
        $proposal = $this->actions->findByToken($token);
        $proposal = $this->actions->update(
            $proposal->tenant_id,
            $proposal->id,
            new CRMProposalDTO(
                $proposal->crm_negotiation_id,
                $proposal->title,
                $proposal->number !== null ? (int) $proposal->number : null,
                'accepted',
                $proposal->valid_until?->format('Y-m-d'),
                $proposal->notes,
                array_values($proposal->items->map(function ($item) {
                    return new \Domain\CRM\DTOs\CRMProposalItemDTO(
                        name: $item->name,
                        quantity: (int) $item->quantity,
                        unit_price: (float) $item->unit_price,
                        discount: (float) ($item->discount ?? 0),
                        position: (int) ($item->position ?? 0),
                        crm_product_id: $item->crm_product_id,
                    );
                })->values()->all())
            )
        );

        return $this->success(new CRMProposalResource($proposal), 'Proposta aceita');
    }

    /**
     * Rejeita proposta publicamente via token (sem autenticação).
     *
     * @param  CRMProposalPublicRequest  $request  Dados da requisição pública.
     * @param  string  $token  Token público da proposta.
     * @return JsonResponse Proposta com status rejeitado.
     */
    public function publicReject(CRMProposalPublicRequest $request, string $token): JsonResponse
    {
        $proposal = $this->actions->findByToken($token);
        $proposal = $this->actions->update(
            $proposal->tenant_id,
            $proposal->id,
            new CRMProposalDTO(
                $proposal->crm_negotiation_id,
                $proposal->title,
                $proposal->number !== null ? (int) $proposal->number : null,
                'rejected',
                $proposal->valid_until?->format('Y-m-d'),
                $proposal->notes,
                array_values($proposal->items->map(function ($item) {
                    return new \Domain\CRM\DTOs\CRMProposalItemDTO(
                        name: $item->name,
                        quantity: (int) $item->quantity,
                        unit_price: (float) $item->unit_price,
                        discount: (float) ($item->discount ?? 0),
                        position: (int) ($item->position ?? 0),
                        crm_product_id: $item->crm_product_id,
                    );
                })->values()->all())
            )
        );

        return $this->success(new CRMProposalResource($proposal), 'Proposta rejeitada');
    }
}
