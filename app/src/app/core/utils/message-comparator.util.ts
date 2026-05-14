/**
 * Utilitário compartilhado para ordenação estável de mensagens.
 *
 * Regra canônica: mensagens são ordenadas por `created_at` e, em caso de empate,
 * por `id` (comparação lexicográfica determinística). Isso garante que mensagens
 * criadas no mesmo milissegundo nunca invertam sua ordem no frontend.
 *
 * Ordem ASC:  created_at ASC → id ASC   (webchat, cronológico)
 * Ordem DESC: created_at DESC → id DESC  (chat interno, mais recente primeiro)
 *
 * @category Utils
 */

/** interface mínima com campos exigidos para ordenação estável. */
export interface StablyOrderedMessage {
  id: string | number;
  created_at?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
}

/**
 * Resolve o timestamp de ordem de uma mensagem.
 *
 * Prioridade: `created_at` → `sent_at` → `delivered_at` → `read_at` → 0.
 * Usa a mesma prioridade que `chat.store.ts` já utilizava em `resolveMessageOrderTimestamp`.
 */
export function resolveMessageOrderTimestamp(message: StablyOrderedMessage): number {
  const candidates = [message.created_at, message.sent_at, message.delivered_at, message.read_at];

  for (const value of candidates) {
    if (typeof value !== 'string' || value.trim() === '') {
      continue;
    }
    const timestamp = Date.parse(value);
    if (!Number.isNaN(timestamp)) {
      return timestamp;
    }
  }

  return 0;
}

/**
 * Compara duas mensagens para ordenação estável ASC (cronológica).
 *
 * Critério 1: `created_at` (timestamp) ASC
 * Critério 2 (desempate): `id` ASC (comparação lexicográfica)
 *
 * @returns número negativo se a < b, positivo se a > b, zero se iguais
 */
export function compareMessagesAsc(a: StablyOrderedMessage, b: StablyOrderedMessage): number {
  const tsDiff = resolveMessageOrderTimestamp(a) - resolveMessageOrderTimestamp(b);
  if (tsDiff !== 0) {
    return tsDiff;
  }

  // Desempate por id (comparação lexicográfica determinística)
  const idA = String(a.id);
  const idB = String(b.id);
  return idA < idB ? -1 : idA > idB ? 1 : 0;
}

/**
 * Compara duas mensagens para ordenação estável DESC (mais recente primeiro).
 *
 * Critério 1: `created_at` (timestamp) DESC
 * Critério 2 (desempate): `id` DESC (comparação lexicográfica invertida)
 *
 * @returns número negativo se a > b, positivo se a < b, zero se iguais
 */
export function compareMessagesDesc(a: StablyOrderedMessage, b: StablyOrderedMessage): number {
  return -compareMessagesAsc(a, b);
}

/**
 * Mescla e ordena duas coleções de mensagens sem duplicatas (ASC).
 *
 * Mensagens com mesmo `id` são deduplicadas, mantendo a versão mais recente.
 * O resultado é ordenado por `created_at + id` de forma estável.
 */
export function mergeAndSortMessagesAsc<T extends StablyOrderedMessage>(
  current: T[],
  incoming: T[],
): T[] {
  const map = new Map<string, T>();

  for (const message of current) {
    map.set(String(message.id), message);
  }
  for (const message of incoming) {
    const key = String(message.id);
    const existing = map.get(key);
    // Mantém a versão mais recente (incoming sobrescreve current)
    if (!existing) {
      map.set(key, message);
    } else {
      map.set(key, { ...existing, ...message });
    }
  }

  return Array.from(map.values()).sort(compareMessagesAsc);
}

/**
 * Mescla e ordena duas coleções de mensagens sem duplicatas (DESC).
 *
 * Mensagens com mesmo `id` são deduplicadas, mantendo a versão mais recente.
 * O resultado é ordenado por `created_at DESC + id DESC` de forma estável.
 */
export function mergeAndSortMessagesDesc<T extends StablyOrderedMessage>(
  current: T[],
  incoming: T[],
): T[] {
  const map = new Map<string, T>();

  for (const message of current) {
    map.set(String(message.id), message);
  }
  for (const message of incoming) {
    const key = String(message.id);
    const existing = map.get(key);
    if (!existing) {
      map.set(key, message);
    } else {
      map.set(key, { ...existing, ...message });
    }
  }

  return Array.from(map.values()).sort(compareMessagesDesc);
}