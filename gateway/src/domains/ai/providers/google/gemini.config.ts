/**
 * Gemini Configuration Service
 *
 * Configuração tipada para Google Gemini com validação em startup.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { GoogleConfiguration } from '../../../../core/config/configuration';

/**
 * Configuração do Gemini com validação
 */
export interface GeminiConfig {
  /**
   * API key do Google AI (obrigatória)
   */
  apiKey: string;

  /**
   * Modelo padrão para chat completions
   * @default "gemini-2.5-flash"
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
export class GeminiConfigService implements OnModuleInit {
  private readonly logger = new Logger(GeminiConfigService.name);
  private config: GeminiConfig;

  constructor(private readonly configService: ConfigService) {
    const googleConfig: GoogleConfiguration =
      this.configService.get<GoogleConfiguration>('google') ??
      ({} as GoogleConfiguration);

    this.config = {
      apiKey: googleConfig.apiKey ?? '',
      defaultModel: googleConfig.model ?? 'gemini-2.5-flash',
      timeoutMs: googleConfig.timeout ?? 180000,
      maxRetries: googleConfig.maxRetries ?? 3,
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
        'GOOGLE_AI_API_KEY is not configured. Gemini provider will not work.',
      );
    } else {
      this.logger.log(
        `Gemini configured with model: ${this.config.defaultModel}, timeout: ${this.config.timeoutMs}ms`,
      );
    }
  }

  /**
   * Retorna a configuração completa
   */
  getConfig(): GeminiConfig {
    return this.config;
  }

  /**
   * Retorna a API key
   */
  getApiKey(): string {
    return this.config.apiKey;
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
