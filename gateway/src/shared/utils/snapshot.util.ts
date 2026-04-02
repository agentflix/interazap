/**
 * Returns true when a hydrated timestamp is within the recency threshold.
 */
export function isRecent(hydratedAt?: string, thresholdMs = 90_000): boolean {
  if (!hydratedAt) {
    return false;
  }

  const hydratedEpoch = Date.parse(hydratedAt);
  if (Number.isNaN(hydratedEpoch)) {
    return false;
  }

  return Date.now() - hydratedEpoch <= thresholdMs;
}
