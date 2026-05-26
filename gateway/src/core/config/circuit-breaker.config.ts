import { CircuitOptions } from '../../shared/services/circuit-breaker';

/**
 * Chaves nomeadas para identificar as configurações de circuit breaker por serviço externo.
 *
 * Contexto: módulo core/config. Utilizado em conjunto com CIRCUIT_BREAKER_CONFIG
 * e getCircuitBreakerOptions para resolver opções por serviço.
 */
export type CircuitBreakerKey =
  | 'openai'
  | 'whatsapp'
  | 'asaas'
  | 'google'
  | 'minimax';

/**
 * Configurações padrão do circuit breaker por serviço externo.
 *
 * Contexto: módulo core/config.
 * - `failureThreshold`: número de falhas consecutivas antes de abrir o circuito
 * - `successThreshold`: número de sucessos consecutivos para fechar o circuito
 * - `resetTimeout`: tempo em ms antes de tentar fechar um circuito aberto
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
 * Retorna as opções de circuit breaker para um serviço externo,
 * mesclando opcionalmente com overrides fornecidos.
 * @param key Chave do serviço externo (ex: `openai`, `whatsapp`)
 * @param overrides Opções parciais a sobrescrever sobre os padrões
 * @returns Objeto CircuitOptions completo combinando defaults e overrides
 */
export const getCircuitBreakerOptions = (
  key: CircuitBreakerKey,
  overrides: CircuitOptions = {},
): CircuitOptions => ({
  ...CIRCUIT_BREAKER_CONFIG[key],
  ...overrides,
});
