import { createHash } from 'crypto';

/**
 * Returns a SHA-256 hash for a given string.
 */
export function sha256(value: string): string {
  return createHash('sha256').update(value).digest('hex');
}

/**
 * Generates deterministic hash for JSON-compatible values.
 */
export function stableHash(value: unknown): string {
  return sha256(stableStringify(value));
}

/**
 * Deterministic JSON stringify with sorted object keys.
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
