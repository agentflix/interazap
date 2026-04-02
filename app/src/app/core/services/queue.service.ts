import { Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  type Observable,
  catchError,
  map,
  tap,
  interval,
  switchMap,
  startWith,
  Subject,
  merge,
} from 'rxjs';
import { environment } from '@env/environment';
import { RealtimeService } from './realtime.service';

export interface QueueMetrics {
  name: string;
  waiting: number;
  active: number;
  completed: number;
  failed: number;
  delayed: number;
  paused: boolean;
  latency?: number;
  throughput?: number;
}

export interface QueueOverview {
  queues: QueueMetrics[];
  totalJobs: number;
  totalFailed: number;
  totalCompleted: number;
  uptime: number;
  redis: {
    connected: boolean;
    memory: string;
  };
}

export interface DeadLetterJob {
  id: string;
  name: string;
  queue: string;
  data: Record<string, unknown>;
  failedReason: string;
  stacktrace: string[];
  attemptsMade: number;
  maxAttempts: number;
  failedAt: string;
  tenant_id?: string;
  processedOn?: string;
  finishedOn?: string;
}

export interface DeadLetterQueueResponse {
  jobs: DeadLetterJob[];
  total: number;
  page: number;
  per_page: number;
  totalPages: number;
}

export interface DeadLetterFilters {
  queue?: string;
  jobName?: string;
  tenant_id?: string;
  from_date?: string;
  to_date?: string;
  page?: number;
  per_page?: number;
}

export type CircuitState = 'CLOSED' | 'OPEN' | 'HALF_OPEN';

export interface CircuitBreakerStatus {
  name: string;
  state: CircuitState;
  failures: number;
  successes: number;
  lastFailure?: string;
  lastSuccess?: string;
  nextRetryAt?: string;
  openedAt?: string;
  halfOpenAt?: string;
  history: CircuitStateChange[];
}

export interface CircuitStateChange {
  from: CircuitState;
  to: CircuitState;
  timestamp: string;
  reason?: string;
}

export interface CircuitBreakerOverview {
  circuits: CircuitBreakerStatus[];
  totalOpen: number;
  totalHalfOpen: number;
  totalClosed: number;
}

export interface QueueActionResult {
  success: boolean;
  message: string;
  affected?: number;
}

interface GatewayCircuitItem {
  name: string;
  state: CircuitState;
  failures: number;
  lastFailure: number | null;
  options?: {
    resetTimeout?: number;
  };
}

interface GatewayCircuitsResponse {
  circuits?: GatewayCircuitItem[];
}

interface GatewayCircuitSingleResponse {
  name: string;
  exists?: boolean;
  state?: CircuitState | null;
  failures?: number;
  lastFailure?: number | null;
  options?: {
    resetTimeout?: number;
  };
}

@Injectable({ providedIn: 'root' })
export class QueueService {
  private readonly http = inject(HttpClient);
  private readonly realtime = inject(RealtimeService);
  private readonly baseUrl = `${environment.apiUrl}/admin/queues`;
  private readonly gatewayBaseUrl =
    environment.gateway.url !== '' ? environment.gateway.url : window.location.origin;
  private readonly circuitsHealthUrl = `${this.gatewayBaseUrl}/health/circuits`;

  private readonly _overview = signal<QueueOverview | null>(null);
  private readonly _circuits = signal<CircuitBreakerOverview | null>(null);
  private readonly _loading = signal(false);
  private readonly _error = signal<string | null>(null);
  private readonly _refreshTrigger = new Subject<void>();

  /** Visao geral de todas as filas (metricas agregadas). */
  readonly overview = this._overview.asReadonly();

  /** Status dos circuit breakers. */
  readonly circuits = this._circuits.asReadonly();

  /** Indica se ha uma operacao de loading em andamento. */
  readonly loading = this._loading.asReadonly();

  /** Ultimo erro encontrado. */
  readonly error = this._error.asReadonly();

  /** Indica se ha conexao realtime ativa. */
  readonly isConnected = computed(() => this.realtime.connected());

  /**
   * Obtem a visa geral de metricas de todas as filas e do Redis.
   *
   * @returns Observable com QueueOverview
   */
  getOverview(): Observable<QueueOverview> {
    return this.http.get<{ data: QueueOverview }>(`${this.baseUrl}/overview`).pipe(
      map((res) => res.data),
      tap((data) => this._overview.set(data)),
    );
  }

  /**
   * Obtem metricas detalhadas de uma fila especifica.
   *
   * @param queueName - Nome da fila
   * @returns Observable com metricas da fila
   */
  getQueueMetrics(queueName: string): Observable<QueueMetrics> {
    return this.http
      .get<{ data: QueueMetrics }>(`${this.baseUrl}/${queueName}/metrics`)
      .pipe(map((res) => res.data));
  }

  /**
   * Pausa uma fila, impedindo que novos jobs sejam processados.
   *
   * @param queueName - Nome da fila
   * @returns Observable com resultado da operacao
   */
  pauseQueue(queueName: string): Observable<QueueActionResult> {
    return this.http
      .post<QueueActionResult>(`${this.baseUrl}/${queueName}/pause`, {})
      .pipe(tap(() => this.refresh()));
  }

  /**
   * Retoma o processamento de uma fila pausada.
   *
   * @param queueName - Nome da fila
   * @returns Observable com resultado da operacao
   */
  resumeQueue(queueName: string): Observable<QueueActionResult> {
    return this.http
      .post<QueueActionResult>(`${this.baseUrl}/${queueName}/resume`, {})
      .pipe(tap(() => this.refresh()));
  }

  /**
   * Limpa jobs de uma fila por status (completed, failed, delayed, wait).
   *
   * @param queueName - Nome da fila
   * @param status - Tipo de jobs a serem removidos
   * @returns Observable com resultado da operacao
   */
  cleanQueue(
    queueName: string,
    status: 'completed' | 'failed' | 'delayed' | 'wait',
  ): Observable<QueueActionResult> {
    return this.http
      .post<QueueActionResult>(`${this.baseUrl}/${queueName}/clean`, { status })
      .pipe(tap(() => this.refresh()));
  }

  /**
   * Lista jobs da fila de dead letter (DLQ) com filtros e paginacao.
   *
   * @param filters - Filtros: queue, jobName, tenant_id, from_date, to_date, page, per_page
   * @returns Observable com lista paginada de jobs falhos
   */
  listDeadLetterJobs(filters?: DeadLetterFilters): Observable<DeadLetterQueueResponse> {
    const params: Record<string, string | number> = {};

    if (filters?.queue) params['queue'] = filters.queue;
    if (filters?.jobName) params['job_name'] = filters.jobName;
    if (filters?.tenant_id) params['tenant_id'] = filters.tenant_id;
    if (filters?.from_date) params['from_date'] = filters.from_date;
    if (filters?.to_date) params['to_date'] = filters.to_date;
    if (filters?.page) params['page'] = filters.page;
    if (filters?.per_page) params['per_page'] = filters.per_page;

    return this.http
      .get<{ data: DeadLetterQueueResponse }>(`${this.baseUrl}/dlq`, { params })
      .pipe(map((res) => res.data));
  }

  /**
   * Retenta a execucao de um job especifico da DLQ.
   *
   * @param jobId - Identificador do job
   * @returns Observable com resultado da operacao
   */
  retryDeadLetterJob(jobId: string): Observable<QueueActionResult> {
    return this.http.post<QueueActionResult>(`${this.baseUrl}/dlq/${jobId}/retry`, {});
  }

  /**
   * Retenta todos os jobs da DLQ conforme filtros.
   *
   * @param filters - Filtros opcionais para filtrar quais jobs seront retentados
   * @returns Observable com resultado da operacao
   */
  retryAllDeadLetterJobs(filters?: DeadLetterFilters): Observable<QueueActionResult> {
    return this.http.post<QueueActionResult>(`${this.baseUrl}/dlq/retry-all`, filters || {});
  }

  /**
   * Remove permanentemente um job da DLQ.
   *
   * @param jobId - Identificador do job
   * @returns Observable com resultado da operacao
   */
  purgeDeadLetterJob(jobId: string): Observable<QueueActionResult> {
    return this.http.delete<QueueActionResult>(`${this.baseUrl}/dlq/${jobId}`);
  }

  /**
   * Remove todos os jobs da DLQ conforme filtros.
   *
   * @param filters - Filtros opcionais
   * @returns Observable com resultado da operacao
   */
  purgeAllDeadLetterJobs(filters?: DeadLetterFilters): Observable<QueueActionResult> {
    return this.http.post<QueueActionResult>(`${this.baseUrl}/dlq/purge-all`, filters || {});
  }

  /**
   * Obtem status de todos os circuit breakers.
   *
   * @returns Observable com CircuitBreakerOverview
   */
  getCircuitBreakers(): Observable<CircuitBreakerOverview> {
    return this.http.get<{ data: CircuitBreakerOverview }>(`${this.baseUrl}/circuits`).pipe(
      map((res) => res.data),
      catchError(() =>
        this.http
          .get<GatewayCircuitsResponse>(this.circuitsHealthUrl)
          .pipe(map((res) => this.mapGatewayCircuitsOverview(res))),
      ),
      tap((data) => this._circuits.set(data)),
    );
  }

  /**
   * Obtem status de um circuit breaker especifico.
   *
   * @param name - Nome do circuit breaker
   * @returns Observable com CircuitBreakerStatus
   */
  getCircuitBreaker(name: string): Observable<CircuitBreakerStatus> {
    return this.http.get<{ data: CircuitBreakerStatus }>(`${this.baseUrl}/circuits/${name}`).pipe(
      map((res) => res.data),
      catchError(() =>
        this.http
          .get<GatewayCircuitSingleResponse>(`${this.circuitsHealthUrl}/${name}`)
          .pipe(map((res) => this.mapGatewayCircuit(res, name))),
      ),
    );
  }

  /**
   * Reseta um circuit breaker para estado CLOSED.
   *
   * @param name - Nome do circuit breaker
   * @returns Observable com resultado da operacao
   */
  resetCircuitBreaker(name: string): Observable<QueueActionResult> {
    return this.http
      .post<QueueActionResult>(`${this.baseUrl}/circuits/${name}/reset`, {})
      .pipe(
        catchError(() =>
          this.http.post<QueueActionResult>(`${this.circuitsHealthUrl}/${name}/reset`, {}),
        ),
      )
      .pipe(tap(() => this.refreshCircuits()));
  }

  /**
   * Forca a abertura (estado OPEN) de um circuit breaker.
   *
   * @param name - Nome do circuit breaker
   * @returns Observable com resultado da operacao
   */
  openCircuitBreaker(name: string): Observable<QueueActionResult> {
    return this.http
      .post<QueueActionResult>(`${this.baseUrl}/circuits/${name}/open`, {})
      .pipe(
        catchError(() =>
          this.http.post<QueueActionResult>(`${this.circuitsHealthUrl}/${name}/open`, {}),
        ),
      )
      .pipe(tap(() => this.refreshCircuits()));
  }

  /**
   * Inicia o polling automatico de metricas de filas.
   *
   * @param intervalMs - Intervalo em milissegundos (default: 5000)
   * @returns Observable que emite QueueOverview a cada intervalo
   */
  startPolling(intervalMs = 5000): Observable<QueueOverview> {
    const polling$ = merge(this._refreshTrigger, interval(intervalMs)).pipe(
      startWith(0),
      switchMap(() => this.getOverview()),
    );

    return polling$;
  }

  /**
   * Inscreve-se para receber eventos de atualizacao de filas via WebSocket.
   *
   * @returns Observable emitindo eventos de fila
   */
  subscribeToQueueEvents(): Observable<{ event: string; data: unknown }> {
    return this.realtime.on<{ event: string; data: unknown }>('queue:update');
  }

  /**
   * Inscreve-se para receber eventos de mudanca de estado de circuit breakers.
   *
   * @returns Observable emitindo CircuitStateChange
   */
  subscribeToCircuitEvents(): Observable<CircuitStateChange & { name: string }> {
    return this.realtime.on<CircuitStateChange & { name: string }>('circuit:state-change');
  }

  /**
   * Inscreve-se para receber notificacoes de novos jobs na DLQ.
   *
   * @returns Observable emitindo DeadLetterJob
   */
  subscribeToDeadLetterEvents(): Observable<DeadLetterJob> {
    return this.realtime.on<DeadLetterJob>('dlq:new-job');
  }

  /**
   * Dispara uma atualizacao manual das metricas de overview.
   */
  refresh(): void {
    this._refreshTrigger.next();
  }

  /**
   * Dispara uma atualizacao manual dos circuit breakers.
   */
  refreshCircuits(): void {
    this.getCircuitBreakers().subscribe();
  }

  /**
   * Limpa todo o estado interno do servico (overview, circuits, error).
   */
  clearState(): void {
    this._overview.set(null);
    this._circuits.set(null);
    this._error.set(null);
  }

  private mapGatewayCircuitsOverview(response: GatewayCircuitsResponse): CircuitBreakerOverview {
    const circuits = (response.circuits ?? []).map((item) =>
      this.mapGatewayCircuit(item, item.name),
    );

    return {
      circuits,
      totalOpen: circuits.filter((circuit) => circuit.state === 'OPEN').length,
      totalHalfOpen: circuits.filter((circuit) => circuit.state === 'HALF_OPEN').length,
      totalClosed: circuits.filter((circuit) => circuit.state === 'CLOSED').length,
    };
  }

  private mapGatewayCircuit(
    response: GatewayCircuitItem | GatewayCircuitSingleResponse,
    fallbackName: string,
  ): CircuitBreakerStatus {
    const state = response.state ?? 'CLOSED';
    const failures = response.failures ?? 0;
    const lastFailureTimestamp = response.lastFailure ?? null;
    const lastFailure =
      typeof lastFailureTimestamp === 'number' && lastFailureTimestamp > 0
        ? new Date(lastFailureTimestamp).toISOString()
        : undefined;
    const resetTimeout = response.options?.resetTimeout ?? 0;
    const nextRetryAt =
      state === 'OPEN' && typeof lastFailureTimestamp === 'number' && lastFailureTimestamp > 0
        ? new Date(lastFailureTimestamp + resetTimeout).toISOString()
        : undefined;

    return {
      name: response.name ?? fallbackName,
      state,
      failures,
      successes: 0,
      lastFailure,
      nextRetryAt,
      history: [],
    };
  }
}
