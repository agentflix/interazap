/**
 * MiniMax Configuration Service
 *
 * Configuração tipada para MiniMax com validação em startup.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { MiniMaxConfiguration } from '../../../../core/config/models/configuration.model';

/**
 * Configuração do MiniMax com validação
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
   * Valida que a configuração está presente (não lança erro, apenas loga warning)
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

  /**
   * Retorna a configuração completa
   */
  getConfig(): MiniMaxConfig {
    return this.config;
  }

  /**
   * Retorna a API key
   */
  getApiKey(): string {
    return this.config.apiKey;
  }

  /**
   * Retorna a base URL da API
   */
  getBaseUrl(): string {
    return this.config.baseUrl;
  }

  /**
   * Retorna o modelo padrão
   */
  getDefaultModel(): string {
    return this.config.defaultModel;
  }

  /**
   * Retorna o timeout em ms
   */
  getTimeoutMs(): number {
    return this.config.timeoutMs;
  }

  /**
   * Retorna o número máximo de retries
   */
  getMaxRetries(): number {
    return this.config.maxRetries;
  }

  /**
   * Verifica se está configurado corretamente
   */
  isConfigured(): boolean {
    return !!this.config.apiKey;
  }
}
