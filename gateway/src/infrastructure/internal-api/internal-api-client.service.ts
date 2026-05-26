import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosInstance, AxiosError } from 'axios';
import * as http from 'http';
import { Histogram } from 'prom-client';
import { MetricsService } from '../../metrics/metrics.service';

interface CircuitBreakerState {
  consecutiveErrors: number;
  openedAt: number | null;
}

/**
 * Cliente HTTP centralizado para comunicação gateway → api/.
 *
 * Contexto: módulo infra/internal-api. Características:
 * - Keep-alive HTTP para reuso de conexões
 * - Header `x-api-key` injetado automaticamente em todas as requisições
 * - Retry exponencial (máx 3 tentativas, delay inicial 100ms × 2^attempt)
 * - Circuit breaker simples (5 erros consecutivos abrem o circuito por 30s)
 * - Histograma Prometheus `gateway_internal_api_duration_seconds`
 */
@Injectable()
export class InternalApiClientService implements OnModuleInit {
  private readonly logger = new Logger(InternalApiClientService.name);

  private readonly maxRetries = 3;
  private readonly baseDelayMs = 100;
  private readonly cbFailureThreshold = 5;
  private readonly cbResetMs = 30_000;

  private axiosInstance!: AxiosInstance;
  private durationHistogram!: Histogram<'operation' | 'status_code'>;

  private readonly cbState: CircuitBreakerState = {
    consecutiveErrors: 0,
    openedAt: null,
  };

  constructor(
    private readonly configService: ConfigService,
    private readonly metricsService: MetricsService,
  ) {}

  /**
   * Inicializa a instância Axios com keep-alive, headers de autenticação e o histograma Prometheus.
   * Lê a URL base, API key, gateway secret e timeout do ConfigService.
   */
  onModuleInit(): void {
    const baseURL =
      this.configService.get<string>('internal.apiUrl') ??
      this.configService.get<string>('api.url') ??
      'http://localhost:8000';
    const apiKey = this.configService.get<string>('internal.apiKey') ?? '';
    const gatewaySecret =
      this.configService.get<string>('gateway.secret') ?? '';
    const timeoutMs =
      this.configService.get<number>('internal.timeoutMs') ?? 5000;

    const keepAliveAgent = new http.Agent({ keepAlive: true });

    this.axiosInstance = axios.create({
      baseURL,
      timeout: timeoutMs,
      httpAgent: keepAliveAgent,
      headers: {
        'x-api-key': apiKey,
        ...(gatewaySecret ? { Authorization: `Bearer ${gatewaySecret}` } : {}),
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });

    this.durationHistogram = new Histogram({
      name: 'gateway_internal_api_duration_seconds',
      help: 'Duration of internal API HTTP calls from gateway to api/',
      labelNames: ['operation', 'status_code'],
      buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5],
      registers: [this.metricsService.getRegistry()],
    });
  }

  /**
   * Executa uma requisição GET para a api/ interna com retry e circuit breaker.
   * @param path Caminho relativo à URL base (ex: `/internal/instances`)
   * @param operation Nome da operação para labels Prometheus e logs
   * @returns Dados tipados da resposta
   */
  async get<T>(path: string, operation: string): Promise<T> {
    return this.execute<T>('get', path, undefined, operation);
  }

  /**
   * Executa uma requisição POST para a api/ interna com retry e circuit breaker.
   * @param path Caminho relativo à URL base
   * @param body Corpo da requisição
   * @param operation Nome da operação para labels Prometheus e logs
   * @returns Dados tipados da resposta
   */
  async post<T>(path: string, body: unknown, operation: string): Promise<T> {
    return this.execute<T>('post', path, body, operation);
  }

  /**
   * Executa uma requisição PATCH para a api/ interna com retry e circuit breaker.
   * @param path Caminho relativo à URL base
   * @param body Corpo da requisição parcial
   * @param operation Nome da operação para labels Prometheus e logs
   * @returns Dados tipados da resposta
   */
  async patch<T>(path: string, body: unknown, operation: string): Promise<T> {
    return this.execute<T>('patch', path, body, operation);
  }

  /**
   * Executa uma requisição HTTP com retry exponencial e circuit breaker.
   * Erros 4xx (exceto 429) não são retentados e abrem o circuito imediatamente.
   * @param method Método HTTP: get, post ou patch
   * @param path Caminho relativo
   * @param body Corpo da requisição (para post/patch)
   * @param operation Nome da operação para observabilidade
   * @returns Dados tipados da resposta
   */
  private async execute<T>(
    method: 'get' | 'post' | 'patch',
    path: string,
    body: unknown,
    operation: string,
  ): Promise<T> {
    this.assertCircuitClosed(operation);

    let lastError: Error | null = null;

    for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
      if (attempt > 0) {
        await this.sleep(this.baseDelayMs * Math.pow(2, attempt - 1));
      }

      const start = Date.now();
      let statusCode = '0';

      try {
        let response;
        if (method === 'get') {
          response = await this.axiosInstance.get<T>(path);
        } else if (method === 'post') {
          response = await this.axiosInstance.post<T>(path, body);
        } else {
          response = await this.axiosInstance.patch<T>(path, body);
        }

        statusCode = String(response.status);
        this.recordDuration(operation, statusCode, start);
        this.onSuccess();
        return response.data;
      } catch (error) {
        const axiosError = error as AxiosError;
        statusCode = axiosError.response
          ? String(axiosError.response.status)
          : 'network_error';

        this.recordDuration(operation, statusCode, start);

        // Não retentar em erros de cliente (4xx) exceto 429
        if (
          axiosError.response &&
          axiosError.response.status >= 400 &&
          axiosError.response.status < 500 &&
          axiosError.response.status !== 429
        ) {
          this.onError();
          throw axiosError;
        }

        lastError = axiosError;

        if (attempt < this.maxRetries) {
          this.logger.warn(
            `[${operation}] attempt ${attempt + 1}/${this.maxRetries + 1} failed (${statusCode}), retrying...`,
          );
        }
      }
    }

    this.onError();
    throw lastError ?? new Error(`[${operation}] all retries exhausted`);
  }

  /**
   * Verifica se o circuit breaker está fechado antes de permitir a requisição.
   * Se estiver aberto e o tempo de reset já tiver expirado, fecha o circuito automaticamente.
   * @param operation Nome da operação (usado na mensagem de erro)
   * @throws Error se o circuito estiver aberto
   */
  private assertCircuitClosed(operation: string): void {
    if (this.cbState.openedAt === null) return;

    const elapsed = Date.now() - this.cbState.openedAt;
    if (elapsed >= this.cbResetMs) {
      this.logger.log(`[circuit-breaker] reset after ${elapsed}ms`);
      this.cbState.openedAt = null;
      this.cbState.consecutiveErrors = 0;
      return;
    }

    this.logger.warn(
      `[circuit-breaker] open — rejeitando ${operation} sem tentar HTTP`,
    );
    throw new Error(
      `InternalApiClientService: circuit breaker OPEN for operation "${operation}"`,
    );
  }

  /** Zera o contador de erros consecutivos após uma requisição bem-sucedida. */
  private onSuccess(): void {
    this.cbState.consecutiveErrors = 0;
  }

  /**
   * Incrementa o contador de erros consecutivos e abre o circuito
   * quando o threshold de falhas é atingido.
   */
  private onError(): void {
    this.cbState.consecutiveErrors++;

    if (this.cbState.consecutiveErrors >= this.cbFailureThreshold) {
      this.cbState.openedAt = Date.now();
      this.logger.error(
        `[circuit-breaker] OPEN após ${this.cbState.consecutiveErrors} erros consecutivos`,
      );
    }
  }

  /**
   * Registra a duração da requisição no histograma Prometheus.
   * @param operation Nome da operação (label)
   * @param statusCode Código HTTP da resposta ou 'network_error' (label)
   * @param startMs Timestamp de início em milissegundos
   */
  private recordDuration(
    operation: string,
    statusCode: string,
    startMs: number,
  ): void {
    const durationSeconds = (Date.now() - startMs) / 1000;
    this.durationHistogram.observe(
      { operation, status_code: statusCode },
      durationSeconds,
    );
  }

  /**
   * Aguarda o tempo especificado antes de tentar nova requisição.
   * @param ms Tempo de espera em milissegundos
   */
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
