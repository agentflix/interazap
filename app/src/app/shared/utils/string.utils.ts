/**
 * Derives up to 2 uppercase initials from a person name.
 *
 * Each word contributes its first letter. Only the first two resulting
 * characters are returned so the output always fits a compact avatar.
 *
 * @param name - Full or partial name string.
 * @param fallback - Value returned when the name produces no initials. Defaults to `''`.
 * @returns Up to 2 uppercase initial characters, or `fallback`.
 *
 * @example
 * getInitials('João Silva')      // 'JS'
 * getInitials('Ana')             // 'A'
 * getInitials('', 'US')          // 'US'
 * getInitials('john doe smith')  // 'JD'
 */
export function getInitials(name: string, fallback = ''): string {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .map((p) => p[0].toUpperCase())
    .join('')
    .slice(0, 2);
  return initials || fallback;
}

/**
 * Formats an ISO date string as a short `pt-BR` locale date (day/month/year).
 *
 * Returns `fallback` when the value is absent or unparseable.
 *
 * @param value - ISO date string (e.g. `'2024-01-15'`) or nullish.
 * @param fallback - Value returned for absent/invalid input. Defaults to `'-'`.
 * @returns Locale-formatted date string or `fallback`.
 *
 * @example
 * formatDate('2024-01-15')         // '15/01/2024'
 * formatDate(null)                 // '-'
 * formatDate('bad', 'N/A')         // 'N/A'
 */
export function formatDate(value?: string | null, fallback = '-'): string {
  if (!value) return fallback;
  // Parse YYYY-MM-DD strings as local dates to avoid UTC timezone shifts
  const isoMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  const date = isoMatch ? new Date(+isoMatch[1], +isoMatch[2] - 1, +isoMatch[3]) : new Date(value);
  return Number.isNaN(date.getTime()) ? fallback : date.toLocaleDateString('pt-BR');
}
