import { type Funnel, type FunnelStep } from 'src/app/core/services/funnel.service';
import { type Negotiation } from 'src/app/core/services/negotiation.service';

/**
 * Tipo de negociação CRM estendendo a base com campos específicos do painel lateral.
 *
 * @remarks
 * Acrescenta valor monetário, data de fechamento prevista, etapa e funil
 * utilizados pela seção CRM do chat.
 */
export type CRMNegotiation = Negotiation & {
  amount?: number;
  expected_close?: string | null;
  expected_close_date?: string | null;
  step?: FunnelStep;
  funnel?: Funnel;
};
