import { sha256 } from './hash.util';

/**
 * Builds a deterministic idempotency key.
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
