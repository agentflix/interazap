/**
 * Type guards e helpers de leitura segura de JsonRecord do gateway.
 * Centraliza a verificação de tipos e extração de campos com conversão automática.
 */
import type { JsonRecord } from '../models/json.model';
export type { JsonRecord };

/**
 * Verifica se um valor é um objeto simples (Record) não-nulo e não-array.
 *
 * @param value - Valor a ser verificado
 * @returns Type guard: true se o valor é `JsonRecord`
 */
export function isRecord(value: unknown): value is JsonRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Converte um valor para `JsonRecord` se possível, retornando `undefined` caso contrário.
 *
 * @param value - Valor a ser convertido
 * @returns `JsonRecord` ou `undefined`
 */
export function asRecord(value: unknown): JsonRecord | undefined {
  return isRecord(value) ? value : undefined;
}

/**
 * Clona profundamente um valor como `JsonRecord` via serialização JSON.
 * Retorna objeto vazio para valores que não são Record.
 *
 * @param value - Valor a ser clonado
 * @returns Cópia profunda do objeto ou `{}`
 */
export function cloneRecord(value: unknown): JsonRecord {
  if (!isRecord(value)) {
    return {};
  }

  return JSON.parse(JSON.stringify(value)) as JsonRecord;
}

/**
 * Lê um campo string de um `JsonRecord` de forma segura.
 *
 * @param source - Objeto de origem
 * @param key - Nome do campo
 * @returns Valor string ou `undefined`
 */
export function getString(source: JsonRecord, key: string): string | undefined {
  const candidate = source[key];
  return typeof candidate === 'string' ? candidate : undefined;
}

/**
 * Lê um campo booleano de um `JsonRecord`, convertendo strings 'true'/'false'.
 *
 * @param source - Objeto de origem
 * @param key - Nome do campo
 * @returns Valor booleano ou `undefined`
 */
export function getBoolean(
  source: JsonRecord,
  key: string,
): boolean | undefined {
  const candidate = source[key];
  if (typeof candidate === 'boolean') {
    return candidate;
  }
  if (typeof candidate === 'string') {
    if (candidate.toLowerCase() === 'true') {
      return true;
    }
    if (candidate.toLowerCase() === 'false') {
      return false;
    }
  }
  return undefined;
}

/**
 * Lê um campo numérico de um `JsonRecord`, convertendo strings numéricas.
 *
 * @param source - Objeto de origem
 * @param key - Nome do campo
 * @returns Valor numérico ou `undefined`
 */
export function getNumber(source: JsonRecord, key: string): number | undefined {
  const candidate = source[key];
  if (typeof candidate === 'number') {
    return candidate;
  }
  if (typeof candidate === 'string') {
    const parsed = Number(candidate);
    if (Number.isFinite(parsed)) {
      return parsed;
    }
  }
  return undefined;
}

/**
 * Lê um campo aninhado do tipo `JsonRecord` de forma segura.
 *
 * @param source - Objeto de origem
 * @param key - Nome do campo
 * @returns Objeto aninhado ou `undefined`
 */
export function getRecord(
  source: JsonRecord,
  key: string,
): JsonRecord | undefined {
  const candidate = source[key];
  return asRecord(candidate);
}

/**
 * Converte um valor desconhecido para string, retornando `undefined` para não-strings.
 *
 * @param value - Valor a ser lido
 * @returns String ou `undefined`
 */
export function readString(value: unknown): string | undefined {
  return typeof value === 'string' ? value : undefined;
}
