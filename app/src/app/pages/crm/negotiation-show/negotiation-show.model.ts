/**
 * Badge rendered in negotiation header.
 */
export interface NegotiationBadge {
  label: string;
  tone: 'success' | 'info' | 'primary' | 'warning';
}

/**
 * Metric card rendered in negotiation header.
 */
export interface MetricCard {
  label: string;
  value: string;
  helper?: string;
}

/**
 * Available tab ids for negotiation details.
 */
export type NegotiationTabId =
  | 'history'
  | 'tasks'
  | 'contacts'
  | 'products'
  | 'files'
  | 'proposals';

import type { Negotiation } from 'src/app/core/services/negotiation.service';

/**
 * Task action metadata for UI rendering.
 */
export interface TaskActionOption {
  id: string;
  label: string;
  icon: string;
}

/**
 * Task status metadata for UI rendering.
 */
export interface TaskStatusOption {
  id: string;
  label: string;
  tone: string;
}

export type NegotiationPayloadResponse = Negotiation | { negotiation: Negotiation };
