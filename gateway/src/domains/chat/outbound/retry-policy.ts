import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RetryOptions, RetryResult } from '../models/retry.model';

export type { RetryOptions, RetryResult };

/**
 * Aplica politica de retentativa com backoff exponencial para operacoes assincronas.
 */
@Injectable()
export class RetryPolicy {
  private readonly logger = new Logger(RetryPolicy.name);
  private readonly defaultOptions: RetryOptions;

  constructor(private readonly configService: ConfigService) {
    this.defaultOptions = {
      maxRetries: Number(
        this.configService.get<string | number>('RETRY_MAX_RETRIES') ?? '3',
      ),
      baseDelayMs: Number(
        this.configService.get<string | number>('RETRY_BASE_DELAY_MS') ??
          '1000',
      ),
      maxDelayMs: Number(
        this.configService.get<string | number>('RETRY_MAX_DELAY_MS') ??
          '60000',
      ),
      exponentialBase: Number(
        this.configService.get<string | number>('RETRY_EXPONENTIAL_BASE') ??
          '4',
      ),
    };
  }

  /**
   * Executa uma operacao com retentativas configuraveis e retorno consolidado.
   *
   * @param operation - Funcao assincrona a ser executada com retentativas
   * @param options - Opcoes parciais que substituem o padrao
   * @returns Resultado consolidado com indicador de sucesso e numero de tentativas
   */
  async execute<T>(
    operation: () => Promise<T>,
    options: Partial<RetryOptions> = {},
  ): Promise<RetryResult<T>> {
    const config = { ...this.defaultOptions, ...options };
    let lastError: Error | undefined;

    for (let attempt = 1; attempt <= config.maxRetries + 1; attempt++) {
      try {
        const data = await operation();
        return {
          success: true,
          data,
          attempts: attempt,
        };
      } catch (error) {
        lastError = error as Error;
        this.logger.warn(
          `Attempt ${attempt}/${config.maxRetries + 1} failed: ${lastError.message}`,
        );

        if (attempt <= config.maxRetries) {
          const delay = this.calculateDelay(attempt, config);
          this.logger.debug(`Waiting ${delay}ms before retry...`);
          await this.sleep(delay);
        }
      }
    }

    return {
      success: false,
      attempts: config.maxRetries + 1,
      lastError,
    };
  }

  /**
   * Calcula o atraso de backoff exponencial para uma tentativa.
   *
   * @param attempt - Numero da tentativa atual (1-based)
   * @param options - Configuracao da politica de retry
   * @returns Atraso em milissegundos limitado ao maximo configurado
   */
  private calculateDelay(attempt: number, options: RetryOptions): number {
    const delay =
      options.baseDelayMs * Math.pow(options.exponentialBase, attempt - 1);
    return Math.min(delay, options.maxDelayMs);
  }

  /**
   * Aguarda o intervalo informado antes da proxima tentativa.
   *
   * @param ms - Tempo de espera em milissegundos
   */
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
