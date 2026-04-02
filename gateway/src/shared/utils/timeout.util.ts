/**
 * Parses timeout values and keeps only finite positive numbers.
 */
export function parsePositiveTimeout(value: unknown, fallback: number): number {
  if (typeof value !== 'number' && typeof value !== 'string') {
    return fallback;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}
