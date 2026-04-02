import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosError } from 'axios';
import { createHmac } from 'crypto';
import {
  OutboundWebhookRequest,
  OutboundWebhookResult,
  WebhookDeliveryAttempt,
} from '../contracts/outbound-webhook.dto';

/**
 * WebhookDispatcherService
 *
 * Serviço responsável pelo despacho de webhooks de saída para sistemas externos.
 * Implementa retry com backoff exponencial configurável, assinatura HMAC-SHA256
 * e agregação de tentativas para auditoria.
 */
@Injectable()
export class WebhookDispatcherService {
  private readonly logger = new Logger(WebhookDispatcherService.name);
  private readonly defaultTimeoutMs: number;
  private readonly defaultMaxRetries: number;
  private readonly retryDelays: number[];

  constructor(private readonly configService: ConfigService) {
    this.defaultTimeoutMs = this.parsePositiveNumber(
      this.configService.get<string | number>('webhooks.dispatchTimeoutMs') ??
        this.configService.get<string | number>('WEBHOOK_DISPATCH_TIMEOUT') ??
        '30000',
      30000,
    );
    this.defaultMaxRetries = this.parseNonNegativeInteger(
      this.configService.get<string | number>('webhooks.dispatchMaxRetries') ??
        this.configService.get<string | number>(
          'WEBHOOK_DISPATCH_MAX_RETRIES',
        ) ??
        '3',
      3,
    );

    const retryDelaysRaw =
      this.configService.get<unknown>('webhooks.dispatchRetryDelays') ??
      this.configService.get<string>('WEBHOOK_DISPATCH_RETRY_DELAYS') ??
      '1000,4000,16000';

    this.retryDelays = this.parseNonNegativeNumberList(
      retryDelaysRaw,
      [1000, 4000, 16000],
    );
  }

  /**
   * Despacha um webhook para o URL externo com retry automático.
   * Não lança exceção em caso de falha — retorna `OutboundWebhookResult.success = false`.
   *
   * @param request - Dados da requisição de webhook (URL, método, headers, body, secret)
   * @returns Resultado final com status, código HTTP e número de tentativas
   */
  async dispatch(
    request: OutboundWebhookRequest,
  ): Promise<OutboundWebhookResult> {
    const maxRetries = this.parseNonNegativeInteger(
      request.maxRetries,
      this.defaultMaxRetries,
    );
    const timeoutMs = this.parsePositiveNumber(
      request.timeoutMs,
      this.defaultTimeoutMs,
    );
    const attempts: WebhookDeliveryAttempt[] = [];

    let lastError: string | undefined;
    let lastStatusCode: number | undefined;

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      const startTime = Date.now();

      try {
        const headers = this.buildHeaders(request);
        const body = request.body ?? {};

        const response = await axios({
          method: request.method,
          url: request.url,
          headers,
          data: body,
          timeout: timeoutMs,
          validateStatus: () => true, // Don't throw on non-2xx
        });

        const duration = Date.now() - startTime;

        attempts.push({
          attemptNumber: attempt + 1,
          statusCode: response.status,
          duration,
          attemptedAt: new Date(),
        });

        if (response.status >= 200 && response.status < 300) {
          this.logger.log(
            `Webhook ${request.id} delivered: ${response.status} in ${duration}ms`,
          );

          return {
            success: true,
            webhookId: request.id,
            statusCode: response.status,
            responseBody:
              typeof response.data === 'string'
                ? response.data
                : JSON.stringify(response.data),
            duration,
            retryCount: attempt,
            completedAt: new Date(),
          };
        }

        lastStatusCode = response.status;
        lastError = `HTTP ${response.status}`;

        this.logger.warn(
          `Webhook ${request.id} attempt ${attempt + 1} failed: ${lastError}`,
        );
      } catch (error) {
        const duration = Date.now() - startTime;
        const axiosError = error as AxiosError;

        lastError = axiosError.message;
        lastStatusCode = axiosError.response?.status;

        attempts.push({
          attemptNumber: attempt + 1,
          statusCode: lastStatusCode,
          error: lastError,
          duration,
          attemptedAt: new Date(),
        });

        this.logger.warn(
          `Webhook ${request.id} attempt ${attempt + 1} error: ${lastError}`,
        );
      }

      // Wait before retry (if not last attempt)
      if (attempt < maxRetries) {
        const delay =
          this.retryDelays[attempt] ??
          this.retryDelays[this.retryDelays.length - 1];
        await this.sleep(delay);
      }
    }

    const totalDuration = attempts.reduce((sum, a) => sum + a.duration, 0);

    this.logger.error(
      `Webhook ${request.id} failed after ${attempts.length} attempts: ${lastError}`,
    );

    return {
      success: false,
      webhookId: request.id,
      statusCode: lastStatusCode,
      error: lastError,
      duration: totalDuration,
      retryCount: attempts.length - 1,
      completedAt: new Date(),
    };
  }

  /**
   * Constrói os headers HTTP para a requisição do webhook,
   * incluindo assinatura HMAC-SHA256 quando `signatureSecret` estiver configurado.
   *
   * @param request - Dados da requisição de webhook
   * @returns Objeto de headers HTTP
   */
  private buildHeaders(
    request: OutboundWebhookRequest,
  ): Record<string, string> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'User-Agent':
        this.configService.get<string>('WEBHOOK_USER_AGENT') ??
        'AgentFlix-Webhook/1.0',
      ...request.headers,
    };

    // Add HMAC signature if secret provided
    if (request.signatureSecret && request.body) {
      const payload = JSON.stringify(request.body);
      const signature = this.computeSignature(payload, request.signatureSecret);
      headers['X-Webhook-Signature'] = signature;
    }

    return headers;
  }

  /**
   * Calcula a assinatura HMAC-SHA256 do payload para validação pelo receptor.
   *
   * @param payload - Payload serializado como string
   * @param secret - Segredo compartilhado para cálculo do HMAC
   * @returns Assinatura no formato `sha256=<hex>`
   */
  private computeSignature(payload: string, secret: string): string {
    const hmac = createHmac('sha256', secret);
    hmac.update(payload);
    return `sha256=${hmac.digest('hex')}`;
  }

  /**
   * Aguarda um intervalo de tempo antes da próxima tentativa.
   *
   * @param ms - Tempo de espera em milissegundos
   */
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Parseia um valor para número positivo com fallback seguro.
   *
   * @param value - Valor a ser parseado
   * @param fallback - Valor padrão em caso de valor inválido
   * @returns Número positivo ou fallback
   */
  private parsePositiveNumber(value: unknown, fallback: number): number {
    if (typeof value !== 'number' && typeof value !== 'string') {
      return fallback;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
  }

  /**
   * Parseia um valor para inteiro não-negativo com fallback seguro.
   *
   * @param value - Valor a ser parseado
   * @param fallback - Valor padrão em caso de valor inválido
   * @returns Inteiro não-negativo ou fallback
   */
  private parseNonNegativeInteger(value: unknown, fallback: number): number {
    if (typeof value !== 'number' && typeof value !== 'string') {
      return fallback;
    }

    const parsed = Number(value);

    if (!Number.isFinite(parsed) || !Number.isInteger(parsed) || parsed < 0) {
      return fallback;
    }

    return parsed;
  }

  /**
   * Parseia um valor para lista de números não-negativos.
   * Aceita array, string separada por vírgula ou número simples.
   *
   * @param value - Valor a ser parseado
   * @param fallback - Array padrão em caso de valor inválido
   * @returns Array de números não-negativos ou fallback
   */
  private parseNonNegativeNumberList(
    value: unknown,
    fallback: number[],
  ): number[] {
    const parsed = Array.isArray(value)
      ? value.map((item) => Number(item))
      : typeof value === 'string'
        ? value
            .split(',')
            .map((item) => Number(item.trim()))
            .filter((item) => !Number.isNaN(item))
        : typeof value === 'number'
          ? [Number(value)]
          : [];

    if (
      parsed.length === 0 ||
      parsed.some((item) => !Number.isFinite(item) || item < 0)
    ) {
      return fallback;
    }

    return parsed;
  }
}
