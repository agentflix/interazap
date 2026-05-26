/**
 * Utilitário de mascaramento de segredos do gateway.
 * Percorre objetos e arrays recursivamente substituindo valores de chaves sensíveis por `'***'`.
 */

/**
 * Mascara recursivamente valores sensíveis em objetos e arrays.
 * Chaves identificadas como secretas têm seu valor substituído por `'***'`.
 *
 * @param value - Valor (objeto, array ou primitivo) a ser mascarado
 * @returns Cópia do valor com dados sensíveis substituídos
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
 * Determina se uma chave de objeto representa um valor sensível.
 * Verifica substrings case-insensitive: token, secret, password, authorization, apikey, api_key.
 *
 * @param key - Chave do objeto a ser avaliada
 * @returns true se a chave é considerada um segredo
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
