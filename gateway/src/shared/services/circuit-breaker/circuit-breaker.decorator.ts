import { Inject } from '@nestjs/common';
import {
  CircuitBreakerService,
  CircuitOptions,
  CircuitOpenException,
} from './circuit-breaker.service';

/**
 * Symbol para armazenar a instância do CircuitBreakerService no contexto de injeção.
 */
export const CIRCUIT_BREAKER_SERVICE = Symbol('CIRCUIT_BREAKER_SERVICE');

/**
 * Representa as opções do decorator @CircuitBreaker, estendendo CircuitOptions com fallback.
 */
export interface CircuitBreakerDecoratorOptions extends CircuitOptions {
  /** Nome do método de fallback a chamar quando o circuito estiver aberto */
  fallbackMethod?: string;
}

/**
 * Decorator de propriedade para injetar o CircuitBreakerService em uma classe.
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
 * Decorator de método que envolve chamadas com proteção de circuit breaker.
 *
 * @param serviceName - Identificador único do circuito
 * @param options - Configuração do circuit breaker
 *
 * @example
 * ```typescript
 * @Injectable()
 * class WhatsAppService {
 *   constructor(private circuitBreaker: CircuitBreakerService) {}
 *
 *   @CircuitBreaker('whatsapp', { failureThreshold: 5, resetTimeout: 30000 })
 *   async sendMessage(phone: string, message: string): Promise<void> {
 *     // Chamada à API externa
 *   }
 *
 *   // Método de fallback opcional
 *   async sendMessageFallback(phone: string, message: string): Promise<void> {
 *     // Enfileirar para retry posterior
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
 * Fábrica de opções de circuit breaker para a API do WhatsApp Business.
 *
 * Usa thresholds conservadores para respeitar os rate limits por minuto do WhatsApp.
 * Abre após 5 falhas consecutivas e aguarda 30 segundos antes de testar a recuperação.
 *
 * @returns Opções de circuito ajustadas para resiliência da API WhatsApp
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
 * Fábrica de opções de circuit breaker para a API da OpenAI.
 *
 * Usa thresholds menores e timeout de reset mais longo porque os custos da API
 * se acumulam rapidamente durante indisponibilidades prolongadas.
 * Abre após 3 falhas e requer apenas 1 chamada bem-sucedida para fechar o circuito.
 *
 * @returns Opções de circuito ajustadas para restrições de custo e disponibilidade da OpenAI
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
 * Fábrica de opções de circuit breaker para provedores de pagamento.
 *
 * Usa thresholds rígidos adequados para transações financeiras. Abre após
 * 3 falhas consecutivas e requer 2 sucessos no estado half-open antes de
 * fechar completamente. A janela de 60 segundos previne tentativas de reconexão precipitadas.
 *
 * @returns Opções de circuito ajustadas para requisitos de confiabilidade de APIs de pagamento
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
