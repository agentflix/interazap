import { CircuitOptions } from '../../shared/services/circuit-breaker';

/**
 * Named keys used to look up circuit breaker configurations for external services.
 */
export type CircuitBreakerKey =
  | 'openai'
  | 'whatsapp'
  | 'asaas'
  | 'google'
  | 'minimax';

/**
 * Default circuit breaker options per external service.
 *
 * @remarks
 * failureThreshold: number of consecutive failures before opening the circuit.
 * successThreshold: number of consecutive successes required to close the circuit.
 * resetTimeout: ms to wait before attempting to close an open circuit.
 */
export const CIRCUIT_BREAKER_CONFIG: Record<CircuitBreakerKey, CircuitOptions> =
  {
    openai: {
      failureThreshold: 5,
      resetTimeout: 60000,
      successThreshold: 2,
    },
    whatsapp: {
      failureThreshold: 5,
      resetTimeout: 30000,
      successThreshold: 2,
    },
    asaas: {
      failureThreshold: 3,
      resetTimeout: 120000,
      successThreshold: 1,
    },
    google: {
      failureThreshold: 5,
      resetTimeout: 60000,
      successThreshold: 2,
    },
    minimax: {
      failureThreshold: 5,
      resetTimeout: 60000,
      successThreshold: 2,
    },
  };

/**
 * Returns the circuit breaker options for a given key, optionally merged with overrides.
 *
 * @param key - The external service whose config to retrieve.
 * @param overrides - Partial options to deep-merge over the defaults.
 * @returns Full CircuitOptions object combining defaults with any overrides.
 */
export const getCircuitBreakerOptions = (
  key: CircuitBreakerKey,
  overrides: CircuitOptions = {},
): CircuitOptions => ({
  ...CIRCUIT_BREAKER_CONFIG[key],
  ...overrides,
});
