/**
 * Serviço de configuração tipada para o provider Google Gemini.
 *
 * Contexto: lê as variáveis `GOOGLE_AI_API_KEY`, modelo padrão, timeout e retries
 * da configuração NestJS e valida a presença da chave de API no `onModuleInit`.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { GoogleConfiguration } from '../../../../core/config/configuration';

/**
 * Interface tipada com os parâmetros de configuração do provider Gemini.
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
   * Valida que a chave de API está presente, logando aviso sem lançar exceção.
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

  /** Retorna o objeto de configuração completo do Gemini. */
  getConfig(): GeminiConfig {
    return this.config;
  }

  /** Retorna a chave de API do Google AI. */
  getApiKey(): string {
    return this.config.apiKey;
  }

  /** Retorna o nome do modelo Gemini padrão configurado. */
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
   * Verifica se o provider Gemini está corretamente configurado.
   * @returns `true` quando a chave de API está presente.
   */
  isConfigured(): boolean {
    return !!this.config.apiKey;
  }
}
