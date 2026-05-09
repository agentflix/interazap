import { type ChatMessageTemplateStatus } from '@core/models/chat-message-template.model';

/** Variantes do `af-badge` aceitas pelo design system. */
export type TemplateBadgeVariant = 'default' | 'success' | 'warning' | 'danger' | 'info';

/**
 * Mapeia o status do template Meta para a variante visual do `af-badge`.
 * Conforme spec DESIGNER (FEAT-050 § 0).
 */
export function templateStatusVariant(
  status: ChatMessageTemplateStatus | string | null | undefined,
): TemplateBadgeVariant {
  switch (status) {
    case 'approved':
      return 'success';
    case 'pending':
    case 'paused':
      return 'warning';
    case 'rejected':
      return 'danger';
    case 'disabled':
      return 'default';
    default:
      return 'default';
  }
}

/**
 * Tradução PT-BR do status para exibição.
 */
export function templateStatusLabel(
  status: ChatMessageTemplateStatus | string | null | undefined,
): string {
  switch (status) {
    case 'approved':
      return 'Aprovado';
    case 'pending':
      return 'Em aprovação';
    case 'rejected':
      return 'Rejeitado';
    case 'paused':
      return 'Pausado pela Meta';
    case 'disabled':
      return 'Desativado';
    default:
      return 'Desconhecido';
  }
}
