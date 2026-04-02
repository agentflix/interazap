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
 * Represents the current status of a single circuit breaker.
 */
interface CircuitStatus {
  /** Unique name identifying the circuit */
  name: string;
  /** Current state of the circuit (CLOSED, OPEN, or HALF_OPEN) */
  state: CircuitState;
  /** Number of consecutive failures recorded */
  failures: number;
  /** Unix timestamp of the last failure, or null if never failed */
  lastFailure: number | null;
  /** Whether the circuit is healthy (state is CLOSED) */
  isHealthy: boolean;
}

/**
 * Response payload for circuit breaker health queries.
 */
interface CircuitHealthResponse {
  /** Overall health indicator — true when all circuits are CLOSED */
  healthy: boolean;
  /** List of individual circuit statuses */
  circuits: CircuitStatus[];
  /** Aggregated counts by state */
  summary: {
    /** Total number of registered circuits */
    total: number;
    /** Number of circuits in CLOSED state */
    closed: number;
    /** Number of circuits in OPEN state */
    open: number;
    /** Number of circuits in HALF_OPEN state */
    halfOpen: number;
  };
}

/**
 * Health check controller for circuit breakers.
 *
 * Provides endpoints to monitor and manage circuit breaker states.
 */
@Controller({ version: '1', path: 'health/circuits' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class CircuitHealthController {
  /**
   * Initializes the controller with the circuit breaker service.
   *
   * @param circuitBreaker - Service managing circuit breaker states
   */
  constructor(private readonly circuitBreaker: CircuitBreakerService) {}

  /**
   * Get status of all circuit breakers.
   *
   * @returns List of all circuits with their current state
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
   * Get status of a specific circuit.
   *
   * @param name - Circuit name
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
   * Reset a circuit to CLOSED state.
   *
   * @param name - Circuit name to reset
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
   * Force a circuit to OPEN state (for testing/maintenance).
   *
   * @param name - Circuit name to open
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
