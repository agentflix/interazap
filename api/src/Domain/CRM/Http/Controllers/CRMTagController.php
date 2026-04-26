<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMTagActions;
use Domain\CRM\DTOs\CRMTagDTO;
use Domain\CRM\Http\Requests\CRMTagRequest;
use Domain\CRM\Http\Resources\CRMTagResource;
use Domain\CRM\Models\CRMTag;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Http\Controllers\Concerns\HandlesCrudOperations;
use Domain\Shared\Support\ListFilterNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para tags CRM.
 *
 * @see CRMTagActions
 */
final class CRMTagController extends BaseController
{
    use HandlesCrudOperations;

    /**
     * @param  CRMTagActions  $actions  Ação de gestão de tags.
     */
    public function __construct(private readonly CRMTagActions $actions) {}

    /**
     * Listar todas as tags ativas.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Lista de tags sem paginação.
     */
    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMTag::class);

        $tenantId = $this->tenantId();
        $tags = $this->actions->all($tenantId)
            ->map(fn (mixed $item) => new CRMTagResource($item));

        return $this->success(['tags' => $tags], 'Tags carregadas');
    }

    /**
     * Listar tags com filtros.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de tags.
     */
    public function index(Request $request): JsonResponse
    {
        $isActive = ListFilterNormalizer::normalizeIsActive($request->input('is_active'), true);

        return $this->crudIndex(
            $request,
            CRMTag::class,
            fn (string $tenantId) => $this->actions->list($tenantId, [
                'search' => $request->input('search'),
                'is_active' => $isActive,
                'per_page' => $request->input('per_page'),
                'sort_by' => $request->input('sort_by'),
                'sort_dir' => $request->input('sort_dir'),
            ]),
            fn (mixed $item) => new CRMTagResource($item),
            'Tags listadas',
        );
    }

    /**
     * Obter detalhes da tag.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da tag.
     * @return JsonResponse Detalhes da tag.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->crudShow(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (mixed $item) => new CRMTagResource($item),
            'Tag recuperada',
        );
    }

    /**
     * Criar nova tag.
     *
     * @param  CRMTagRequest  $request  Requisição com dados da tag.
     * @return JsonResponse Tag criada.
     */
    public function store(CRMTagRequest $request): JsonResponse
    {
        return $this->crudStore(
            $request,
            CRMTag::class,
            fn (string $tenantId) => $this->actions->create($tenantId, CRMTagDTO::fromRequest($request)),
            fn (mixed $item) => new CRMTagResource($item),
            'Tag criada',
        );
    }

    /**
     * Atualizar tag.
     *
     * @param  CRMTagRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID da tag.
     * @return JsonResponse Tag atualizada.
     */
    public function update(CRMTagRequest $request, string $id): JsonResponse
    {
        return $this->crudUpdate(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->update($tenantId, $modelId, CRMTagDTO::fromRequest($request)),
            fn (mixed $item) => new CRMTagResource($item),
            'Tag atualizada',
        );
    }

    /**
     * Excluir tag.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da tag.
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
     * Vincular tag a um contato.
     *
     * @param  Request  $request  Requisição com ID da tag.
     * @param  string  $contactId  ID do contato.
     * @return JsonResponse Confirmação.
     */
    public function attachToContact(Request $request, string $contactId): JsonResponse
    {
        $this->authorize('update', CRMTag::class);

        $tenantId = $this->tenantId();
        $request->validate(['tag_id' => ['required', 'uuid', 'exists:crm_tags,id']]);
        $this->actions->attachToContact($tenantId, $contactId, (string) $request->string('tag_id'));

        return $this->success([], 'Tag vinculada ao contato');
    }

    /**
     * Desvincular tag de um contato.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $contactId  ID do contato.
     * @param  string  $tagId  ID da tag.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function detachFromContact(Request $request, string $contactId, string $tagId): JsonResponse
    {
        $this->authorize('update', CRMTag::class);

        $tenantId = $this->tenantId();
        $this->actions->detachFromContact($tenantId, $contactId, $tagId);

        return $this->noContent();
    }

    /**
     * Vincular tag a uma empresa.
     *
     * @param  Request  $request  Requisição com ID da tag.
     * @param  string  $companyId  ID da empresa.
     * @return JsonResponse Confirmação.
     */
    public function attachToCompany(Request $request, string $companyId): JsonResponse
    {
        $this->authorize('update', CRMTag::class);

        $tenantId = $this->tenantId();
        $request->validate(['tag_id' => ['required', 'uuid', 'exists:crm_tags,id']]);
        $this->actions->attachToCompany($tenantId, $companyId, (string) $request->string('tag_id'));

        return $this->success([], 'Tag vinculada à empresa');
    }

    /**
     * Desvincular tag de uma empresa.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $companyId  ID da empresa.
     * @param  string  $tagId  ID da tag.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function detachFromCompany(Request $request, string $companyId, string $tagId): JsonResponse
    {
        $this->authorize('update', CRMTag::class);

        $tenantId = $this->tenantId();
        $this->actions->detachFromCompany($tenantId, $companyId, $tagId);

        return $this->noContent();
    }

    /**
     * Vincular tag a uma negociação.
     *
     * @param  Request  $request  Requisição com ID da tag.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Confirmação.
     */
    public function attachToNegotiation(Request $request, string $negotiationId): JsonResponse
    {
        $this->authorize('update', CRMTag::class);

        $tenantId = $this->tenantId();
        $request->validate(['tag_id' => ['required', 'uuid', 'exists:crm_tags,id']]);
        $this->actions->attachToNegotiation($tenantId, $negotiationId, (string) $request->string('tag_id'));

        return $this->success([], 'Tag vinculada à negociação');
    }

    /**
     * Desvincular tag de uma negociação.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $negotiationId  ID da negociação.
     * @param  string  $tagId  ID da tag.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function detachFromNegotiation(Request $request, string $negotiationId, string $tagId): JsonResponse
    {
        $this->authorize('update', CRMTag::class);

        $tenantId = $this->tenantId();
        $this->actions->detachFromNegotiation($tenantId, $negotiationId, $tagId);

        return $this->noContent();
    }
}
