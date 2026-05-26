import type { NegotiationBadge } from './negotiation-show.model';

/**
 * Retorna as classes Tailwind correspondentes ao tom do badge.
 */
export function badgeClass(badge: NegotiationBadge): string {
  switch (badge.tone) {
    case 'success':
      return 'bg-success/10 text-success border-success/20';
    case 'info':
      return 'bg-info/10 text-info border-info/20';
    case 'warning':
      return 'bg-warning/10 text-warning border-warning/20';
    default:
      return 'bg-primary/10 text-primary border-primary/20';
  }
}

/**
 * Retorna o rótulo legível do status da negociação.
 */
export function getStatusLabel(status?: string): string {
  switch (status) {
    case 'won':
      return 'Ganha';
    case 'lost':
      return 'Perdida';
    default:
      return 'Aberta';
  }
}

/**
 * Converte um valor de data para string no formato yyyy-mm-dd.
 */
export function formatDateInput(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toISOString().split('T')[0];
}

/**
 * Normaliza valores de hora para o formato HH:mm.
 */
export function formatTimeInput(value: string): string {
  if (!value) return '';
  return value.length >= 5 ? value.slice(0, 5) : value;
}

/**
 * Normaliza IDs provenientes de controles HTML (string ou número).
 */
export function normalizeId(value: string | number | null): string | number | null {
  if (value === null || value === '') return null;
  if (typeof value === 'string') {
    const numeric = Number(value);
    return Number.isNaN(numeric) ? value : numeric;
  }
  return value;
}

/**
 * Compara dois IDs de forma segura, permitindo equivalência entre string e número.
 */
export function isSameId(left?: string | number | null, right?: string | number | null): boolean {
  if (left === null || left === undefined) return false;
  if (right === null || right === undefined) return false;
  return String(left) === String(right);
}
