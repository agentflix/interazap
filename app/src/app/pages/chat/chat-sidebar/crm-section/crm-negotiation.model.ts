import { type Funnel, type FunnelStep } from 'src/app/core/services/funnel.service';
import { type Negotiation } from 'src/app/core/services/negotiation.service';

/**
 * CRM Negotiation type extending the base Negotiation with additional fields.
 *
 * @remarks
 * Adds amount, expected close date, step and funnel references
 * specific to the CRM section of the chat sidebar.
 */
export type CRMNegotiation = Negotiation & {
  amount?: number;
  expected_close?: string | null;
  expected_close_date?: string | null;
  step?: FunnelStep;
  funnel?: Funnel;
};
