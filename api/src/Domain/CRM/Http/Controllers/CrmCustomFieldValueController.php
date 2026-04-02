<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMCustomFieldValueActions;
use Domain\CRM\DTOs\CRMCustomFieldValueDTO;
use Domain\CRM\Http\Requests\CRMCustomFieldValueRequest;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para valores de campos personalizados.
 */
final class CRMCustomFieldValueController extends BaseController
{
    public function __construct(private readonly CRMCustomFieldValueActions $actions) {}

    public function upsertForContact(CRMCustomFieldValueRequest $request, string $contactId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($contactId);
        $this->authorize('update', $contact);

        $value = $this->actions->upsert(
            $tenantId,
            CRMCustomFieldValueDTO::fromRequest($request, CRMContact::class, $contactId)
        );

        return $this->success($value->toArray(), 'Campo salvo para contato');
    }

    public function upsertForCompany(CRMCustomFieldValueRequest $request, string $companyId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $company = CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($companyId);
        $this->authorize('update', $company);

        $value = $this->actions->upsert(
            $tenantId,
            CRMCustomFieldValueDTO::fromRequest($request, CRMCompany::class, $companyId)
        );

        return $this->success($value->toArray(), 'Campo salvo para empresa');
    }

    public function upsertForNegotiation(CRMCustomFieldValueRequest $request, string $negotiationId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);
        $this->authorize('update', $negotiation);

        $value = $this->actions->upsert(
            $tenantId,
            CRMCustomFieldValueDTO::fromRequest($request, CRMNegotiation::class, $negotiationId)
        );

        return $this->success($value->toArray(), 'Campo salvo para negociação');
    }
}
