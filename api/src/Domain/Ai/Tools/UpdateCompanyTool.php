<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMCompany;

/**
 * Tool to update company data.
 */
class UpdateCompanyTool implements AiToolInterface
{
    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $companyId = (string) ($input->parameters['company_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($companyId === '') {
            return ToolResultDTO::failure('Company ID is required');
        }

        $company = CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->find($companyId);

        if (! $company) {
            return ToolResultDTO::failure('Company not found');
        }

        $updatableFields = ['name', 'document', 'email', 'phone', 'address', 'city', 'state', 'zip_code'];
        $updates = [];

        foreach ($updatableFields as $field) {
            if (! array_key_exists($field, $input->parameters)) {
                continue;
            }

            $value = $input->parameters[$field];
            if (is_string($value)) {
                $value = trim($value);
            }

            $updates[$field] = $value;
        }

        if ($updates === []) {
            return ToolResultDTO::failure('No valid fields informed to update');
        }

        $company->update($updates);

        return ToolResultDTO::success(
            message: 'Company updated successfully',
            data: [
                'company_id' => $company->id,
                'updated_fields' => array_keys($updates),
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::UPDATE_COMPANY;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Updates company profile data in CRM.';
    }

    /**
     * Return expected tool parameters.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'company_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of company to update',
            ],
            'name' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company name',
            ],
            'document' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company document',
            ],
            'email' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company email',
            ],
            'phone' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company phone',
            ],
            'address' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company address',
            ],
            'city' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company city',
            ],
            'state' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company state',
            ],
            'zip_code' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company zip code',
            ],
        ];
    }
}
