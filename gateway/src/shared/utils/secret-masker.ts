/**
 * Masks sensitive values recursively from objects and arrays.
 */
export function maskSecrets(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map((item) => maskSecrets(item));
  }

  if (!value || typeof value !== 'object') {
    return value;
  }

  const input = value as Record<string, unknown>;
  const output: Record<string, unknown> = {};

  for (const [key, entry] of Object.entries(input)) {
    if (isSecretKey(key)) {
      output[key] = '***';
      continue;
    }

    output[key] = maskSecrets(entry);
  }

  return output;
}

/**
 * Determines whether a given object key represents a sensitive value.
 *
 * Matches keys containing case-insensitive substrings: token, secret,
 * password, authorization, apikey, or api_key.
 *
 * @param key - The object key to evaluate
 * @returns true if the key is considered a secret
 */
function isSecretKey(key: string): boolean {
  const normalized = key.toLowerCase();

  return (
    normalized.includes('token') ||
    normalized.includes('secret') ||
    normalized.includes('password') ||
    normalized.includes('authorization') ||
    normalized.includes('apikey') ||
    normalized.includes('api_key')
  );
}
