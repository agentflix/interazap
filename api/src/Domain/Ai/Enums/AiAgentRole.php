<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Papéis (roles) disponíveis para agentes de IA.
 */
enum AiAgentRole: string
{
    case SALES_QUALIFIER = 'sales_qualifier';
    case SUPPORT_L1 = 'support_l1';
    case CS_RETENTION = 'cs_retention';
    case POST_SALES = 'post_sales';
    case APPOINTMENT = 'appointment';
    case GENERAL = 'general';

    /**
     * Retorna o label legível para o usuário.
     */
    public function label(): string
    {
        return match ($this) {
            self::SALES_QUALIFIER => 'Sales Qualifier',
            self::SUPPORT_L1 => 'Support L1',
            self::CS_RETENTION => 'CS Retention',
            self::POST_SALES => 'Post Sales',
            self::APPOINTMENT => 'Appointment',
            self::GENERAL => 'General',
        };
    }

    /**
     * Retorna a descrição do papel.
     */
    public function description(): string
    {
        return match ($this) {
            self::SALES_QUALIFIER => 'Agente focado em qualificação de leads e prospecção.',
            self::SUPPORT_L1 => 'Agente de suporte nível 1 para atendimento inicial.',
            self::CS_RETENTION => 'Agente de retenção de clientes e sucesso do cliente.',
            self::POST_SALES => 'Agente de pós-venda e acompanhamento.',
            self::APPOINTMENT => 'Agente focado em agendamento de reuniões.',
            self::GENERAL => 'Agente geral com acesso completo a todas as ferramentas.',
        };
    }
}
