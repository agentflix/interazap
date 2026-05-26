/**
 * Serviço de configuração tipada para o provider MiniMax.
 *
 * Contexto: lê as variáveis `MINIMAX_API_KEY`, base URL, modelo padrão, timeout e retries
 * da configuração NestJS e valida a presença da chave de API no `onModuleInit`.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { MiniMaxConfiguration } from '../../../../core/config/models/configuration.model';

/**
 * Interface tipada com os parâmetros de configuração do provider MiniMax.
 */
export interface MiniMaxConfig {
  /**
   * API key do MiniMax (obrigatória)
   */
  apiKey: string;

  /**
   * Base URL da API MiniMax
   * @default "https://api.minimax.io"
   */
  baseUrl: string;

  /**
   * Modelo padrão para chat completions
   * @default "MiniMax-M2.5"
   */
  defaultModel: string;

  /**
   * Timeout em milliseconds
   * @default 180000 (3 minutos)
   */
  timeoutMs: number;

  /**
   * Número máximo de retries
   * @default 3
   */
  maxRetries: number;
}

@Injectable()
export class MiniMaxConfigService implements OnModuleInit {
  private readonly logger = new Logger(MiniMaxConfigService.name);
  private config: MiniMaxConfig;

  constructor(private readonly configService: ConfigService) {
    const minimaxConfig: MiniMaxConfiguration =
      this.configService.get<MiniMaxConfiguration>('minimax') ??
      ({} as MiniMaxConfiguration);

    this.config = {
      apiKey: minimaxConfig.apiKey ?? '',
      baseUrl: minimaxConfig.baseUrl ?? 'https://api.minimax.io',
      defaultModel: minimaxConfig.model ?? 'MiniMax-M2.5',
      timeoutMs: minimaxConfig.timeout ?? 180000,
      maxRetries: minimaxConfig.maxRetries ?? 3,
    };
  }

  onModuleInit(): void {
    this.validateConfiguration();
  }

  /**
   * Valida que a chave de API está presente, logando aviso sem lançar exceção.
   */
  private validateConfiguration(): void {
    if (!this.config.apiKey) {
      this.logger.warn(
        'MINIMAX_API_KEY is not configured. MiniMax provider will not work.',
      );
    } else {
      this.logger.log(
        `MiniMax configured with model: ${this.config.defaultModel}, timeout: ${this.config.timeoutMs}ms`,
      );
    }
  }

  /** Retorna o objeto de configuração completo do MiniMax. */
  getConfig(): MiniMaxConfig {
    return this.config;
  }

  /** Retorna a chave de API do MiniMax. */
  getApiKey(): string {
    return this.config.apiKey;
  }

  /** Retorna a URL base da API MiniMax. */
  getBaseUrl(): string {
    return this.config.baseUrl;
  }

  /** Retorna o nome do modelo MiniMax padrão configurado. */
  getDefaultModel(): string {
    return this.config.defaultModel;
  }

  /** Retorna o timeout configurado em milissegundos. */
  getTimeoutMs(): number {
    return this.config.timeoutMs;
  }

  /** Retorna o número máximo de tentativas configurado. */
  getMaxRetries(): number {
    return this.config.maxRetries;
  }

  /**
   * Verifica se o provider MiniMax está corretamente configurado.
   * @returns `true` quando a chave de API está presente.
   */
  isConfigured(): boolean {
    return !!this.config.apiKey;
  }
}
