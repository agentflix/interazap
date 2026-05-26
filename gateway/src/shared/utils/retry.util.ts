/**
 * Utilitário de retry do gateway.
 * Fornece execução de operações assíncronas com retry e backoff linear.
 */

/**
 * Executa uma operação assíncrona com retry e backoff linear.
 * A operação é tentada até `attempts` vezes; a cada falha aguarda `delayMs * tentativa` ms antes de tentar novamente.
 *
 * @param operation - Função assíncrona a executar, recebe o número da tentativa atual (base 1)
 * @param attempts - Número máximo de tentativas (padrão: 3)
 * @param delayMs - Delay base em milissegundos para backoff linear (padrão: 250)
 * @returns Resultado da operação bem-sucedida
 * @throws Último erro lançado pela operação ao esgotar as tentativas
 */
export async function retryAsync<T>(
  operation: (attempt: number) => Promise<T>,
  attempts = 3,
  delayMs = 250,
): Promise<T> {
  const maxAttempts = Math.max(1, attempts);
  let lastError: unknown;

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    try {
      return await operation(attempt);
    } catch (error) {
      lastError = error;

      if (attempt === maxAttempts) {
        break;
      }

      await wait(delayMs * attempt);
    }
  }

  throw lastError;
}

/**
 * Pausa a execução pelo número de milissegundos especificado.
 *
 * @param ms - Duração da pausa em milissegundos
 * @returns Promise que resolve após o delay
 */
function wait(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}
