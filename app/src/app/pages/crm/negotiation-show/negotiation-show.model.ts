/**
 * Badge exibido no cabeçalho da negociação.
 */
export interface NegotiationBadge {
  label: string;
  tone: 'success' | 'info' | 'primary' | 'warning';
}

/**
 * Cartão de métrica exibido no cabeçalho da negociação.
 */
export interface MetricCard {
  label: string;
  value: string;
  helper?: string;
}

/**
 * Identificadores de abas disponíveis nos detalhes da negociação.
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
 * Metadados de ação de tarefa para renderização na UI.
 */
export interface TaskActionOption {
  id: string;
  label: string;
  icon: string;
}

/**
 * Metadados de status de tarefa para renderização na UI.
 */
export interface TaskStatusOption {
  id: string;
  label: string;
  tone: string;
}

export type NegotiationPayloadResponse = Negotiation | { negotiation: Negotiation };
