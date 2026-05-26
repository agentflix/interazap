/**
 * Utilitário de composição de chaves de idempotência do gateway.
 * Gera chaves determinísticas via hash SHA-256 a partir de partes fornecidas.
 */
import { sha256 } from './hash.util';

/**
 * Constrói uma chave de idempotência determinística a partir de partes fornecidas.
 * Partes nulas, indefinidas ou vazias são ignoradas; as demais são unidas com `|` e hasheadas com SHA-256.
 *
 * @param parts - Partes que compõem a chave (strings, números, null ou undefined)
 * @returns Hash SHA-256 hexadecimal da combinação das partes válidas
 */
export function composeIdempotencyKey(
  parts: Array<string | number | null | undefined>,
): string {
  const base = parts
    .filter((part) => part !== null && part !== undefined)
    .map((part) => String(part).trim())
    .filter((part) => part.length > 0)
    .join('|');

  return sha256(base);
}
