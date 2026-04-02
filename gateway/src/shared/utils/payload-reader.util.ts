import type { ChatMessageRole } from '../models/chat.model';
export type { ChatMessageRole };

/**
 * Converte um valor desconhecido para `Record<string, unknown>`.
 * Retorna objeto vazio se o valor não for um objeto simples.
 *
 * @param payload - Valor a ser convertido
 * @returns Objeto associativo ou `{}` para valores inválidos
 */
export function toRecord(payload: unknown): Record<string, unknown> {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    return {};
  }

  return payload as Record<string, unknown>;
}

/**
 * Parseia um valor para array JSON.
 * Aceita arrays nativos ou strings JSON que representem arrays.
 *
 * @param value - Valor a ser parseado
 * @returns Array parseado ou o valor original se não for array válido
 */
export function parseJsonArray(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value;
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return value;
  }

  try {
    const parsed = JSON.parse(value) as unknown;
    return Array.isArray(parsed) ? parsed : value;
  } catch {
    return value;
  }
}

/**
 * Parseia um valor para objeto JSON.
 * Aceita objetos nativos ou strings JSON que representem objetos.
 *
 * @param value - Valor a ser parseado
 * @returns Objeto parseado ou `undefined` se o valor não for objeto válido
 */
export function parseJsonObject(
  value: unknown,
): Record<string, unknown> | undefined {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return undefined;
  }

  try {
    const parsed = JSON.parse(value) as unknown;
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      return parsed as Record<string, unknown>;
    }
    return undefined;
  } catch {
    return undefined;
  }
}

/**
 * Extrai um campo string de um payload, retornando `undefined` para valores vazios.
 *
 * @param payload - Payload de origem
 * @param key - Nome do campo
 * @returns Valor string do campo ou `undefined`
 */
export function getStringField(
  payload: unknown,
  key: string,
): string | undefined {
  const value = toRecord(payload)[key];
  return typeof value === 'string' && value !== '' ? value : undefined;
}

/**
 * Extrai um campo númerico de um payload, convertendo strings numéricas.
 *
 * @param payload - Payload de origem
 * @param key - Nome do campo
 * @returns Valor numérico do campo ou `undefined` se inválido
 */
export function getNumberField(
  payload: unknown,
  key: string,
): number | undefined {
  const value = toRecord(payload)[key];

  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : undefined;
  }

  return undefined;
}

/**
 * Extrai um campo booleano de um payload, convertendo strings 'true'/'false'.
 *
 * @param payload - Payload de origem
 * @param key - Nome do campo
 * @returns Valor booleano do campo ou `undefined`
 */
export function getBooleanField(
  payload: unknown,
  key: string,
): boolean | undefined {
  const value = toRecord(payload)[key];

  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (normalized === 'true') {
      return true;
    }
    if (normalized === 'false') {
      return false;
    }
  }

  return undefined;
}

/**
 * Extrai um campo array de strings de um payload.
 * Aceita arrays nativos ou strings JSON que representem arrays de strings.
 *
 * @param payload - Payload de origem
 * @param key - Nome do campo
 * @returns Array de strings não vazias ou `undefined`
 */
export function getStringArrayField(
  payload: unknown,
  key: string,
): string[] | undefined {
  const rawValue = toRecord(payload)[key];
  const value = parseJsonArray(rawValue);
  if (!Array.isArray(value)) {
    return undefined;
  }

  const normalized = value.filter(
    (item): item is string => typeof item === 'string' && item !== '',
  );

  return normalized.length > 0 ? normalized : undefined;
}

/**
 * Extrai o campo `messages` de um payload, validando estrutura de chat.
 * Cada item deve ter `role` válido ('system' | 'user' | 'assistant') e `content` string.
 *
 * @param payload - Payload de origem
 * @returns Array de mensagens de chat válidas ou `undefined`
 */
export function getMessagesField(
  payload: unknown,
): Array<{ role: ChatMessageRole; content: string }> | undefined {
  const rawMessages = toRecord(payload)['messages'];
  const messages = parseJsonArray(rawMessages);
  if (!Array.isArray(messages)) {
    return undefined;
  }

  return messages
    .map((message) => {
      if (!message || typeof message !== 'object' || Array.isArray(message)) {
        return null;
      }

      const record = message as Record<string, unknown>;
      const role = record['role'];
      const content = record['content'];

      if (
        (role === 'system' || role === 'user' || role === 'assistant') &&
        typeof content === 'string'
      ) {
        return {
          role,
          content,
        };
      }

      return null;
    })
    .filter(
      (item): item is { role: ChatMessageRole; content: string } =>
        item !== null,
    );
}
