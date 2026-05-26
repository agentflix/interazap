import {
  Controller,
  Get,
  Param,
  Post,
  HttpCode,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import {
  CircuitBreakerService,
  CircuitState,
} from '../shared/services/circuit-breaker';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';

/**
 * Representa o status atual de um circuit breaker individual.
 */
interface CircuitStatus {
  /** Nome único que identifica o circuito. */
  name: string;
  /** Estado atual do circuito (CLOSED, OPEN ou HALF_OPEN). */
  state: CircuitState;
  /** Número de falhas consecutivas registradas. */
  failures: number;
  /** Timestamp Unix da última falha, ou null se nunca falhou. */
  lastFailure: number | null;
  /** Indica se o circuito está saudável (estado CLOSED). */
  isHealthy: boolean;
}

/**
 * Payload de resposta para consultas de saúde dos circuit breakers.
 */
interface CircuitHealthResponse {
  /** Indicador geral de saúde — true quando todos os circuitos estão CLOSED. */
  healthy: boolean;
  /** Lista de status individuais de cada circuito. */
  circuits: CircuitStatus[];
  /** Contagens agregadas por estado. */
  summary: {
    /** Número total de circuitos registrados. */
    total: number;
    /** Número de circuitos em estado CLOSED. */
    closed: number;
    /** Número de circuitos em estado OPEN. */
    open: number;
    /** Número de circuitos em estado HALF_OPEN. */
    halfOpen: number;
  };
}

/**
 * Controller de health check para circuit breakers.
 *
 * Contexto: módulo health. Expõe endpoints para monitoramento e administração
 * dos estados dos circuit breakers registrados no gateway.
 * Protegido pelo guard de API key interna.
 */
@Controller({ version: '1', path: 'health/circuits' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class CircuitHealthController {
  constructor(private readonly circuitBreaker: CircuitBreakerService) {}

  /**
   * GET /health/circuits
   * Retorna o status de todos os circuit breakers registrados.
   * @returns Lista completa de circuitos com estado atual e resumo agregado
   */
  @Get()
  getAll(): CircuitHealthResponse {
    const circuits = this.circuitBreaker.getAllCircuits();

    const status: CircuitHealthResponse = {
      healthy: true,
      circuits: [],
      summary: {
        total: 0,
        closed: 0,
        open: 0,
        halfOpen: 0,
      },
    };

    for (const [name, circuit] of Object.entries(circuits)) {
      status.circuits.push({
        name,
        ...circuit,
        isHealthy: circuit.state === CircuitState.CLOSED,
      });

      status.summary.total++;
      switch (circuit.state) {
        case CircuitState.CLOSED:
          status.summary.closed++;
          break;
        case CircuitState.OPEN:
          status.summary.open++;
          status.healthy = false;
          break;
        case CircuitState.HALF_OPEN:
          status.summary.halfOpen++;
          break;
      }
    }

    return status;
  }

  /**
   * GET /health/circuits/:name
   * Retorna o status de um circuit breaker específico pelo nome.
   * @param name Nome do circuito
   * @returns Status do circuito ou `{ exists: false }` se não encontrado
   */
  @Get(':name')
  getOne(@Param('name') name: string) {
    const state = this.circuitBreaker.getState(name);

    if (!state) {
      return {
        name,
        exists: false,
        state: null,
      };
    }

    const circuits = this.circuitBreaker.getAllCircuits();
    return {
      name,
      exists: true,
      ...circuits[name],
    };
  }

  /**
   * POST /health/circuits/:name/reset
   * Força o reset de um circuit breaker para o estado CLOSED.
   * @param name Nome do circuito a ser resetado
   * @returns `{ success: true }` se o circuito foi encontrado e resetado
   */
  @Post(':name/reset')
  @HttpCode(200)
  reset(@Param('name') name: string) {
    const success = this.circuitBreaker.reset(name);

    return {
      name,
      success,
      message: success
        ? `Circuit ${name} has been reset to CLOSED state`
        : `Circuit ${name} not found`,
    };
  }

  /**
   * POST /health/circuits/:name/open
   * Força um circuit breaker para o estado OPEN (para testes ou manutenção).
   * @param name Nome do circuito a ser aberto
   * @returns `{ success: true }` se o circuito foi encontrado e aberto
   */
  @Post(':name/open')
  @HttpCode(200)
  forceOpen(@Param('name') name: string) {
    const success = this.circuitBreaker.forceOpen(name);

    return {
      name,
      success,
      message: success
        ? `Circuit ${name} has been forced to OPEN state`
        : `Circuit ${name} not found`,
    };
  }
}
