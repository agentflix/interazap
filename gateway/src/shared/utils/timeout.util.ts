/**
 * Utilitário de parsing de timeout do gateway.
 * Garante que valores de timeout configurados sejam sempre números finitos positivos.
 */

/**
 * Parseia valores de timeout, retornando apenas números finitos positivos.
 * Aceita strings numéricas e números; qualquer valor inválido retorna o fallback.
 *
 * @param value - Valor a ser parseado (número, string ou outro)
 * @param fallback - Valor padrão retornado quando `value` não é um número finito positivo
 * @returns Número finito positivo parseado ou `fallback`
 */
export function parsePositiveTimeout(value: unknown, fallback: number): number {
  if (typeof value !== 'number' && typeof value !== 'string') {
    return fallback;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}
