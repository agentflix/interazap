/**
 * Executes an async operation with retry and linear backoff.
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
 * Pauses execution for the specified number of milliseconds.
 *
 * @param ms - Duration to sleep in milliseconds
 * @returns Promise that resolves after the delay
 */
function wait(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}
