<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiAgentRole;
use Domain\Ai\Services\AiPermissionMatrixService;

describe('AiPermissionMatrixService', function (): void {
    beforeEach(function (): void {
        $this->service = new AiPermissionMatrixService;
    });

    it('returns the correct preset tools for sales_qualifier', function (): void {
        $preset = $this->service->getAvailableTools(AiAgentRole::SALES_QUALIFIER);

        expect($preset)->toBeArray()
            ->and($preset)->toContain('qualify_lead')
            ->and($preset)->toContain('create_negotiation')
            ->and($preset)->not->toContain('close_ticket');
    });

    it('returns the correct preset tools for support_l1', function (): void {
        $preset = $this->service->getAvailableTools(AiAgentRole::SUPPORT_L1);

        expect($preset)->toBeArray()
            ->and($preset)->toContain('close_ticket')
            ->and($preset)->toContain('read_ticket')
            ->and($preset)->not->toContain('create_negotiation');
    });

    it('returns the correct preset tools for cs_retention and general', function (): void {
        $csPreset = $this->service->getAvailableTools(AiAgentRole::CS_RETENTION);
        $generalPreset = $this->service->getAvailableTools(AiAgentRole::GENERAL);

        expect($csPreset)->toBeArray()
            ->and($csPreset)->toContain('qualify_lead')
            ->and($generalPreset)->toBeArray()
            ->and($generalPreset)->toContain('create_proposal');
    });

    it('returns the correct preset tools for post_sales', function (): void {
        $preset = $this->service->getAvailableTools(AiAgentRole::POST_SALES);

        expect($preset)->toBeArray()
            ->and($preset)->toContain('get_negotiation_info')
            ->and($preset)->not->toContain('create_negotiation');
    });

    it('returns the correct preset tools for appointment', function (): void {
        $preset = $this->service->getAvailableTools(AiAgentRole::APPOINTMENT);

        expect($preset)->toBeArray()
            ->and($preset)->toContain('schedule_event')
            ->and($preset)->not->toContain('create_company');
    });

    it('returns all tool names with expanded inventory', function (): void {
        $allTools = $this->service->getAllToolNames();

        expect($allTools)->toHaveCount(29)
            ->and($allTools)->toContain('move_pipeline')
            ->and($allTools)->toContain('create_proposal')
            ->and($allTools)->toContain('link_contact_to_company')
            ->and($allTools)->toContain('delegate_to_agent');
    });
});
