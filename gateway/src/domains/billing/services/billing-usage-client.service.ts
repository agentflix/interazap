import { Injectable, Logger, Optional } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosError } from 'axios';
import { BillingUsageMetrics } from '../../../metrics/billing-usage.metrics';

export interface UsageCheckResult {
  allowed: boolean;
  current: number;
  limit: number;
  mode: string;
  is_overage: boolean;
}

export interface FailOpenLogPayload {
  tenant_id: string;
  ai_turn_id: string;
  channel: string;
  reason: string;
  attempted_at: string;
}

/**
 * BillingUsageClient
 *
 * Cliente HTTP para o endpoint de check-and-increment de uso de mensagens IA.
 * Implementa retry exponencial e fallback fail-open.
 */
@Injectable()
export class BillingUsageClient {
  private readonly logger = new Logger(BillingUsageClient.name);
  private readonly apiUrl: string;
  private readonly s2sToken: string;
  private readonly timeoutMs: number;

  constructor(
    private readonly configService: ConfigService,
    @Optional() private readonly billingMetrics?: BillingUsageMetrics,
  ) {
    this.apiUrl =
      this.configService.get<string>('api.url') ?? 'http://localhost:8000';
    this.s2sToken = this.configService.get<string>('api.s2sToken') ?? '';
    this.timeoutMs =
      this.configService.get<number>('api.authTimeoutMs') ?? 1500;
  }

  /**
   * Verifica se o tenant pode enviar uma mensagem IA e incrementa o contador.
   *
   * Retry 3x exponencial: 200ms → 1s → 5s
   * Fallback fail-open: se todas falharem, retorna allowed=true e registra falha.
   *
   * @param tenantId - UUID do tenant
   * @param channel - Canal de comunicação (whatsapp, telegram, etc.)
   * @param aiTurnId - ID único do turno de IA
   * @returns Resultado do check com allowed, current, limit, mode, is_overage
   */
  async checkAndIncrement(
    tenantId: string,
    channel: string,
    aiTurnId: string,
  ): Promise<UsageCheckResult> {
    const url = `${this.apiUrl}/api/v1/billing/usage/check-and-increment`;
    const payload = {
      tenant_id: tenantId,
      channel,
      ai_turn_id: aiTurnId,
    };

    const delays = [200, 1000, 5000];
    const startTime = Date.now();

    for (let attempt = 0; attempt < delays.length; attempt++) {
      try {
        const response = await axios.post<{
          success: boolean;
          message: string;
          data: UsageCheckResult;
        }>(url, payload, {
          headers: {
            'Content-Type': 'application/json',
            'X-Internal-Api-Key': this.s2sToken,
          },
          timeout: this.timeoutMs,
        });

        const result = response.data.data;
        const durationMs = Date.now() - startTime;
        this.billingMetrics?.recordUsageCheckDuration(tenantId, durationMs);
        this.billingMetrics?.recordAiMessage(
          tenantId,
          result.mode,
          result.allowed,
        );

        this.logger.log({
          event: 'usage.check',
          tenantId,
          channel,
          aiTurnId,
          allowed: result.allowed,
          attempt: attempt + 1,
        });

        return result;
      } catch (error: unknown) {
        const isAxiosError = axios.isAxiosError(error);
        const status = isAxiosError
          ? (error as AxiosError).response?.status
          : undefined;

        this.logger.warn({
          event: 'usage.check_retry',
          tenantId,
          channel,
          aiTurnId,
          attempt: attempt + 1,
          status,
          error: isAxiosError ? (error as AxiosError).message : String(error),
        });

        if (attempt < delays.length - 1) {
          await this.sleep(delays[attempt]);
        }
      }
    }

    // Fallback fail-open: permite a mensagem mas registra a falha
    this.logger.error({
      event: 'usage.fail_open',
      tenantId,
      channel,
      aiTurnId,
      reason: 'all_retries_exhausted',
    });

    this.billingMetrics?.recordUsageCheckFailure('all_retries_exhausted');
    this.billingMetrics?.recordAiMessage(tenantId, 'fail_open', true);

    this.logFailure({
      tenant_id: tenantId,
      ai_turn_id: aiTurnId,
      channel,
      reason: 'all_retries_exhausted',
      attempted_at: new Date().toISOString(),
    }).catch(() => {
      // logFailure already logs internally; swallow to avoid unhandled rejection
    });

    const durationMs = Date.now() - startTime;
    this.billingMetrics?.recordUsageCheckDuration(tenantId, durationMs);

    return {
      allowed: true,
      current: -1,
      limit: -1,
      mode: 'fail_open',
      is_overage: false,
    };
  }

  /**
   * Registra um fail-open no endpoint da API para reconciliação futura.
   *
   * Chamado sem retry — fire-and-forget.
   *
   * @param payload - Dados do fail-open
   */
  async logFailure(payload: FailOpenLogPayload): Promise<void> {
    const url = `${this.apiUrl}/api/v1/billing/usage/fail-open-log`;

    try {
      await axios.post(url, payload, {
        headers: {
          'Content-Type': 'application/json',
          'X-Internal-Api-Key': this.s2sToken,
        },
        timeout: this.timeoutMs,
      });

      this.logger.log({
        event: 'usage.fail_open_logged',
        aiTurnId: payload.ai_turn_id,
        tenantId: payload.tenant_id,
      });
    } catch (error: unknown) {
      const msg = error instanceof Error ? error.message : String(error);
      this.logger.error({
        event: 'usage.fail_open_log_failed',
        aiTurnId: payload.ai_turn_id,
        tenantId: payload.tenant_id,
        error: msg,
      });
    }
  }

  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
