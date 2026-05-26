/**
 * Utilitário de snapshot/recência do gateway.
 * Verifica se dados hidratados/cacheados ainda estão dentro do limiar de recência configurado.
 */

/**
 * Retorna true quando um timestamp de hidratação está dentro do limiar de recência.
 * Utilizado para verificar se dados cacheados/hidratados ainda são considerados recentes.
 *
 * @param hydratedAt - Timestamp ISO 8601 da última hidratação
 * @param thresholdMs - Limiar de recência em milissegundos (padrão: 90000 ms = 90 s)
 * @returns true se o timestamp está dentro do limiar; false se ausente, inválido ou expirado
 */
export function isRecent(hydratedAt?: string, thresholdMs = 90_000): boolean {
  if (!hydratedAt) {
    return false;
  }

  const hydratedEpoch = Date.parse(hydratedAt);
  if (Number.isNaN(hydratedEpoch)) {
    return false;
  }

  return Date.now() - hydratedEpoch <= thresholdMs;
}
