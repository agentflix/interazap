import { Injectable, Logger } from '@nestjs/common';
import {
  CircuitState,
  CircuitStateChangeHandler,
  CircuitOptions,
  CircuitInfo,
} from '../../models/circuit-breaker.model';

export { CircuitState };
export type { CircuitStateChangeHandler, CircuitOptions };

/**
 * Serviço que implementa o padrão Circuit Breaker para chamadas externas no NestJS.
 *
 * Protege chamadas a serviços externos rastreando falhas e evitando
 * falhas em cascata quando os serviços estão indisponíveis.
 *
 * Estados:
 * - CLOSED: Operação normal, requisições passam normalmente.
 * - OPEN: Circuito aberto, requisições falham imediatamente.
 * - HALF_OPEN: Testando se o serviço se recuperou.
 */
@Injectable()
export class CircuitBreakerService {
  private readonly logger = new Logger(CircuitBreakerService.name);
  private readonly circuits = new Map<string, CircuitInfo>();

  private readonly defaultOptions: Required<CircuitOptions> = {
    failureThreshold: 5,
    resetTimeout: 30000,
    successThreshold: 2,
    name: 'default',
    onStateChange: () => undefined,
  };

  /**
   * Executa uma função com proteção de circuit breaker.
   *
   * @param serviceName - Identificador único do circuito
   * @param fn - Função assíncrona a ser executada
   * @param options - Configurações do circuito
   * @param fallback - Função de fallback opcional quando o circuito está aberto
   * @returns Resultado da função ou resultado do fallback
   */
  async call<T>(
    serviceName: string,
    fn: () => Promise<T>,
    options: CircuitOptions = {},
    fallback?: () => Promise<T>,
  ): Promise<T> {
    const circuit = this.getOrCreateCircuit(serviceName, options);

    if (circuit.state === CircuitState.OPEN) {
      if (this.shouldAttemptReset(circuit)) {
        this.transitionTo(circuit, CircuitState.HALF_OPEN);
      } else {
        this.logger.warn(`Circuit ${serviceName} is OPEN, failing fast`);
        if (fallback) {
          return fallback();
        }
        throw new CircuitOpenException(serviceName);
      }
    }

    try {
      const result = await fn();
      this.onSuccess(circuit);
      return result;
    } catch (error) {
      this.onFailure(circuit, error);
      throw error;
    }
  }

  /**
   * Retorna o estado atual de um circuito.
   *
   * @param serviceName - Nome do serviço/circuito
   * @returns Estado atual do circuito ou undefined se não existir
   */
  getState(serviceName: string): CircuitState | undefined {
    return this.circuits.get(serviceName)?.state;
  }

  /**
   * Retorna todos os circuitos com seus status atuais.
   *
   * @returns Mapa de nome do circuito para informações de status
   */
  getAllCircuits(): Record<
    string,
    {
      state: CircuitState;
      failures: number;
      lastFailure: number | null;
      options: CircuitOptions;
    }
  > {
    const result: Record<string, any> = {};

    this.circuits.forEach((circuit, name) => {
      result[name] = {
        state: circuit.state,
        failures: circuit.failures,
        lastFailure: circuit.lastFailure || null,
        options: {
          failureThreshold: circuit.options.failureThreshold,
          resetTimeout: circuit.options.resetTimeout,
          successThreshold: circuit.options.successThreshold,
        },
      };
    });

    return result;
  }

  /**
   * Reseta manualmente um circuito para o estado CLOSED.
   *
   * @param serviceName - Nome do serviço/circuito
   * @returns true se o circuito foi resetado, false se não existia
   */
  reset(serviceName: string): boolean {
    const circuit = this.circuits.get(serviceName);
    if (!circuit) {
      return false;
    }

    this.transitionTo(circuit, CircuitState.CLOSED);
    circuit.failures = 0;
    circuit.successes = 0;
    this.logger.log(`Circuit ${serviceName} manually reset`);
    return true;
  }

  /**
   * Força um circuito ao estado OPEN (para testes ou manutenção).
   *
   * @param serviceName - Nome do serviço/circuito
   * @returns true se o circuito foi aberto, false se não existia
   */
  forceOpen(serviceName: string): boolean {
    const circuit = this.circuits.get(serviceName);
    if (!circuit) {
      return false;
    }

    this.transitionTo(circuit, CircuitState.OPEN);
    circuit.lastFailure = Date.now();
    this.logger.warn(`Circuit ${serviceName} manually opened`);
    return true;
  }

  /**
   * Retorna um circuito existente ou cria um novo com as opções mescladas com os defaults.
   *
   * @param serviceName - Identificador único do circuito
   * @param options - Configuração do circuito (mesclada com os defaults)
   * @returns Registro do circuito para o nome de serviço fornecido
   */
  private getOrCreateCircuit(
    serviceName: string,
    options: CircuitOptions,
  ): CircuitInfo {
    let circuit = this.circuits.get(serviceName);

    if (!circuit) {
      circuit = {
        state: CircuitState.CLOSED,
        failures: 0,
        successes: 0,
        lastFailure: 0,
        lastStateChange: Date.now(),
        options: { ...this.defaultOptions, ...options, name: serviceName },
      };
      this.circuits.set(serviceName, circuit);
      this.logger.debug(`Circuit ${serviceName} created`);
    }

    return circuit;
  }

  /**
   * Determina se o circuito deve transitar de OPEN para HALF_OPEN.
   *
   * O circuito tenta reset apenas após o timeout de reset configurado ter decorrido
   * desde a última falha.
   *
   * @param circuit - Registro do circuito a ser avaliado
   * @returns true quando tempo suficiente passou para tentar um reset
   */
  private shouldAttemptReset(circuit: CircuitInfo): boolean {
    const timeSinceLastFailure = Date.now() - circuit.lastFailure;
    return timeSinceLastFailure >= circuit.options.resetTimeout;
  }

  /**
   * Registra uma chamada bem-sucedida no circuito.
   *
   * No estado HALF_OPEN, incrementa o contador de sucessos e fecha o circuito
   * quando o threshold de sucesso é atingido. No estado CLOSED, reseta o
   * contador de falhas para permitir operação normal.
   *
   * @param circuit - Registro do circuito a ser atualizado
   */
  private onSuccess(circuit: CircuitInfo): void {
    if (circuit.state === CircuitState.HALF_OPEN) {
      circuit.successes++;
      if (circuit.successes >= circuit.options.successThreshold) {
        this.transitionTo(circuit, CircuitState.CLOSED);
        circuit.failures = 0;
        circuit.successes = 0;
      }
    } else if (circuit.state === CircuitState.CLOSED) {
      // Reset failure count on success in closed state
      circuit.failures = 0;
    }
  }

  /**
   * Registra uma chamada com falha no circuito.
   *
   * Incrementa o contador de falhas e abre o circuito quando o threshold de falhas é atingido.
   * No estado HALF_OPEN, qualquer falha reabre imediatamente o circuito.
   *
   * @param circuit - Registro do circuito a ser atualizado
   * @param error - Erro que causou a falha (registrado no log, não armazenado persistentemente)
   */
  private onFailure(circuit: CircuitInfo, error: unknown): void {
    circuit.failures++;
    circuit.lastFailure = Date.now();

    if (circuit.state === CircuitState.HALF_OPEN) {
      // Any failure in half-open immediately opens circuit
      this.transitionTo(circuit, CircuitState.OPEN);
      circuit.successes = 0;
    } else if (circuit.state === CircuitState.CLOSED) {
      if (circuit.failures >= circuit.options.failureThreshold) {
        this.transitionTo(circuit, CircuitState.OPEN);
      }
    }

    this.logger.warn(
      `Circuit ${circuit.options.name} failure #${circuit.failures}: ${error instanceof Error ? error.message : 'Unknown error'}`,
    );
  }

  /**
   * Transita o circuito para um novo estado, registrando o timestamp da mudança.
   *
   * Invoca o callback `onStateChange` fornecido pelo usuário (se houver) e silencia
   * qualquer erro que ele lance para que as transições de estado nunca cascateiem.
   *
   * @param circuit - Registro do circuito a ser atualizado
   * @param newState - Estado destino (CLOSED, OPEN ou HALF_OPEN)
   */
  private transitionTo(circuit: CircuitInfo, newState: CircuitState): void {
    const oldState = circuit.state;
    if (oldState === newState) {
      return;
    }

    circuit.state = newState;
    circuit.lastStateChange = Date.now();

    this.logger.log(
      `Circuit ${circuit.options.name} transitioned: ${oldState} -> ${newState}`,
    );

    try {
      circuit.options.onStateChange(circuit.options.name, oldState, newState);
    } catch (error) {
      this.logger.warn(
        `Circuit ${circuit.options.name} state listener failed: ${error instanceof Error ? error.message : String(error)}`,
      );
    }
  }
}

/**
 * Exceção lançada quando o circuit breaker está aberto e nenhum fallback foi fornecido.
 */
export class CircuitOpenException extends Error {
  constructor(public readonly serviceName: string) {
    super(`Circuit breaker is open for service: ${serviceName}`);
    this.name = 'CircuitOpenException';
  }
}
