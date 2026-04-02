import { Inject } from '@nestjs/common';
import {
  CircuitBreakerService,
  CircuitOptions,
  CircuitOpenException,
} from './circuit-breaker.service';

/**
 * Symbol for storing circuit breaker service instance.
 */
export const CIRCUIT_BREAKER_SERVICE = Symbol('CIRCUIT_BREAKER_SERVICE');

/**
 * Decorator options for @CircuitBreaker
 */
export interface CircuitBreakerDecoratorOptions extends CircuitOptions {
  /** Fallback method name to call when circuit is open */
  fallbackMethod?: string;
}

/**
 * Property decorator to inject CircuitBreakerService.
 * Use this on a class property to get the service instance.
 *
 * @example
 * ```typescript
 * @Injectable()
 * class MyService {
 *   @InjectCircuitBreaker()
 *   private circuitBreaker: CircuitBreakerService;
 * }
 * ```
 */
export function InjectCircuitBreaker(): PropertyDecorator {
  return Inject(CircuitBreakerService);
}

/**
 * Method decorator that wraps method calls with circuit breaker protection.
 *
 * @param serviceName - Unique identifier for this circuit
 * @param options - Circuit breaker configuration
 *
 * @example
 * ```typescript
 * @Injectable()
 * class WhatsAppService {
 *   constructor(private circuitBreaker: CircuitBreakerService) {}
 *
 *   @CircuitBreaker('whatsapp', { failureThreshold: 5, resetTimeout: 30000 })
 *   async sendMessage(phone: string, message: string): Promise<void> {
 *     // External API call
 *   }
 *
 *   // Optional fallback method
 *   async sendMessageFallback(phone: string, message: string): Promise<void> {
 *     // Queue for later retry
 *   }
 * }
 * ```
 */
export function CircuitBreaker(
  serviceName: string,
  options: CircuitBreakerDecoratorOptions = {},
): MethodDecorator {
  return function (
    _target: object,
    propertyKey: string | symbol,
    descriptor: PropertyDescriptor,
  ) {
    const originalMethod = descriptor.value as (
      ...args: unknown[]
    ) => Promise<unknown>;
    const fallbackMethodName =
      options.fallbackMethod || `${String(propertyKey)}Fallback`;

    descriptor.value = async function (
      this: Record<string, unknown>,
      ...args: unknown[]
    ): Promise<unknown> {
      // Get circuit breaker service from instance
      const circuitBreaker = (this.circuitBreaker || this._circuitBreaker) as
        | CircuitBreakerService
        | undefined;

      if (!circuitBreaker) {
        // If no circuit breaker injected, just call original method
        return originalMethod.apply(this, args) as Promise<unknown>;
      }

      // Check for fallback method
      const fallbackMethod = this[fallbackMethodName] as
        | ((...args: unknown[]) => Promise<unknown>)
        | undefined;
      const fallback =
        typeof fallbackMethod === 'function'
          ? (): Promise<unknown> =>
              fallbackMethod.apply(this, args) as Promise<unknown>
          : undefined;

      return circuitBreaker.call(
        serviceName,
        (): Promise<unknown> =>
          originalMethod.apply(this, args) as Promise<unknown>,
        options,
        fallback,
      );
    };

    return descriptor;
  };
}

/**
 * Create circuit breaker options for WhatsApp API.
 * Uses conservative thresholds due to rate limiting.
 */
/**
 * Circuit breaker options factory for the WhatsApp Business API.
 *
 * Uses conservative thresholds to account for WhatsApp's per-minute rate limits.
 * Opens after 5 consecutive failures and waits 30 seconds before testing recovery.
 *
 * @returns Circuit options tuned for WhatsApp API resilience
 */
export function whatsAppCircuitOptions(): CircuitOptions {
  return {
    failureThreshold: 5,
    resetTimeout: 30000,
    successThreshold: 2,
    name: 'whatsapp',
  };
}

/**
 * Create circuit breaker options for OpenAI API.
 * Uses lower thresholds and longer reset due to API costs.
 */
/**
 * Circuit breaker options factory for the OpenAI API.
 *
 * Uses lower thresholds and a longer reset timeout because API costs accumulate
 * quickly during prolonged outages. Opens after 3 failures and requires only
 * 1 successful call to close the circuit.
 *
 * @returns Circuit options tuned for OpenAI API cost and availability constraints
 */
export function openAICircuitOptions(): CircuitOptions {
  return {
    failureThreshold: 3,
    resetTimeout: 60000,
    successThreshold: 1,
    name: 'openai',
  };
}

/**
 * Create circuit breaker options for payment providers.
 * Uses strict thresholds for financial operations.
 */
/**
 * Circuit breaker options factory for payment gateway providers.
 *
 * Uses strict thresholds appropriate for financial transactions. Opens after
 * 3 consecutive failures and requires 2 successes in half-open state before
 * fully closing. The 60-second reset window prevents rapid reconnection attempts.
 *
 * @returns Circuit options tuned for payment API reliability requirements
 */
export function paymentCircuitOptions(): CircuitOptions {
  return {
    failureThreshold: 3,
    resetTimeout: 60000,
    successThreshold: 2,
    name: 'payment',
  };
}

export { CircuitOpenException };
