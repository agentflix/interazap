/**
 * Utilitários de hash do gateway.
 * Fornece SHA-256 simples e serialização JSON determinística com chaves ordenadas.
 */
import { createHash } from 'crypto';

/**
 * Retorna o hash SHA-256 de uma string.
 *
 * @param value - String a ser hasheada
 * @returns Hash hexadecimal SHA-256
 */
export function sha256(value: string): string {
  return createHash('sha256').update(value).digest('hex');
}

/**
 * Gera um hash SHA-256 determinístico para valores compatíveis com JSON.
 * A serialização usa chaves ordenadas para garantir resultado estável independente da ordem de inserção.
 *
 * @param value - Valor a ser hasheado
 * @returns Hash hexadecimal SHA-256 estável
 */
export function stableHash(value: unknown): string {
  return sha256(stableStringify(value));
}

/**
 * Serializa um valor para JSON de forma determinística, com chaves de objetos ordenadas alfabeticamente.
 *
 * @param value - Valor a ser serializado
 * @returns String JSON estável com chaves ordenadas
 */
export function stableStringify(value: unknown): string {
  if (Array.isArray(value)) {
    return `[${value.map((item) => stableStringify(item)).join(',')}]`;
  }

  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>;
    const keys = Object.keys(record).sort();
    const pairs = keys.map(
      (key) => `${JSON.stringify(key)}:${stableStringify(record[key])}`,
    );
    return `{${pairs.join(',')}}`;
  }

  return JSON.stringify(value) ?? 'null';
}
