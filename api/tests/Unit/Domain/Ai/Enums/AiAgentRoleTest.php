<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiAgentRole;

describe('AiAgentRole', function (): void {
    it('has all 6 required roles', function (): void {
        $roles = AiAgentRole::cases();

        expect($roles)->toHaveCount(6)
            ->and(AiAgentRole::SALES_QUALIFIER->value)->toBe('sales_qualifier')
            ->and(AiAgentRole::SUPPORT_L1->value)->toBe('support_l1')
            ->and(AiAgentRole::CS_RETENTION->value)->toBe('cs_retention')
            ->and(AiAgentRole::POST_SALES->value)->toBe('post_sales')
            ->and(AiAgentRole::APPOINTMENT->value)->toBe('appointment')
            ->and(AiAgentRole::GENERAL->value)->toBe('general');
    });

    it('returns correct labels for each role', function (): void {
        expect(AiAgentRole::SALES_QUALIFIER->label())->toBe('Sales Qualifier')
            ->and(AiAgentRole::SUPPORT_L1->label())->toBe('Support L1')
            ->and(AiAgentRole::CS_RETENTION->label())->toBe('CS Retention')
            ->and(AiAgentRole::POST_SALES->label())->toBe('Post Sales')
            ->and(AiAgentRole::APPOINTMENT->label())->toBe('Appointment')
            ->and(AiAgentRole::GENERAL->label())->toBe('General');
    });

    it('returns correct description for each role', function (): void {
        expect(AiAgentRole::SALES_QUALIFIER->description())->toContain('qualificação')
            ->and(AiAgentRole::SUPPORT_L1->description())->toContain('suporte')
            ->and(AiAgentRole::CS_RETENTION->description())->toContain('retenção')
            ->and(AiAgentRole::POST_SALES->description())->toContain('pós-venda')
            ->and(AiAgentRole::APPOINTMENT->description())->toContain('agendamento')
            ->and(AiAgentRole::GENERAL->description())->toContain('geral');
    });
});
