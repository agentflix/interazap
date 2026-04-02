/**
 * Formats a numeric value to BRL currency.
 */
export function formatCurrency(value?: number | null): string {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  }).format(value ?? 0);
}
