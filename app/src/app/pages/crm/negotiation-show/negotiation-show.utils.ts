import type { NegotiationBadge } from './negotiation-show.model';

/**
 * Resolve tailwind classes by badge tone.
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
 * Human-readable negotiation status.
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
 * Convert date-like value to yyyy-mm-dd string.
 */
export function formatDateInput(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toISOString().split('T')[0];
}

/**
 * Normalize time values to HH:mm.
 */
export function formatTimeInput(value: string): string {
  if (!value) return '';
  return value.length >= 5 ? value.slice(0, 5) : value;
}

/**
 * Normalize id from HTML controls.
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
 * Compare ids safely, allowing numeric/string equivalents.
 */
export function isSameId(left?: string | number | null, right?: string | number | null): boolean {
  if (left === null || left === undefined) return false;
  if (right === null || right === undefined) return false;
  return String(left) === String(right);
}
