/**
 * Deriva até 2 iniciais maiúsculas a partir de um nome de pessoa.
 *
 * Cada palavra contribui com sua primeira letra. Apenas os dois primeiros
 * caracteres resultantes são retornados para caber em um avatar compacto.
 *
 * @param name - Nome completo ou parcial.
 * @param fallback - Valor retornado quando o nome não produz iniciais. Padrão `''`.
 * @returns Até 2 caracteres iniciais em maiúscula, ou `fallback`.
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
 * Formata uma string de data ISO como data curta no locale `pt-BR` (dia/mês/ano).
 *
 * Retorna `fallback` quando o valor está ausente ou não é parseável.
 *
 * @param value - String de data ISO (ex.: `'2024-01-15'`) ou nulo.
 * @param fallback - Valor retornado para entrada ausente/inválida. Padrão `'-'`.
 * @returns String de data formatada no locale ou `fallback`.
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
