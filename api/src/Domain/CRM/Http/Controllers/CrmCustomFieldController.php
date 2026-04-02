<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMCustomFieldActions;
use Domain\CRM\DTOs\CRMCustomFieldDTO;
use Domain\CRM\Http\Requests\CRMCustomFieldRequest;
use Domain\CRM\Http\Resources\CRMCustomFieldResource;
use Domain\CRM\Models\CRMCustomField;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para campos personalizados do CRM.
 */
final class CRMCustomFieldController extends BaseController
{
    public function __construct(private readonly CRMCustomFieldActions $actions) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMCustomField::class);

        $tenantId = $this->tenantId();
        $paginator = $this->actions->list($tenantId);
        $paginator->getCollection()->transform(fn ($item) => new CRMCustomFieldResource($item));

        return $this->paginated($paginator, 'Campos personalizados listados');
    }

    public function store(CRMCustomFieldRequest $request): JsonResponse
    {
        $this->authorize('create', CRMCustomField::class);

        $tenantId = $this->tenantId();
        $field = $this->actions->create($tenantId, CRMCustomFieldDTO::fromRequest($request));

        return $this->created(new CRMCustomFieldResource($field), 'Campo criado');
    }

    public function update(CRMCustomFieldRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $field = $this->actions->find($tenantId, $id);
        $this->authorize('update', $field);

        $field = $this->actions->update($tenantId, $id, CRMCustomFieldDTO::fromRequest($request));

        return $this->success(new CRMCustomFieldResource($field), 'Campo atualizado');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $field = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $field);

        $this->actions->delete($tenantId, $id);

        return $this->noContent();
    }
}
