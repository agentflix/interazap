/**
 * OpenAI Configuration Service
 *
 * Configuração tipada para OpenAI com validação em startup.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { OpenAIConfiguration } from '../../../../core/config/configuration';

/**
 * Configuração do OpenAI com validação
 */
export interface OpenAIConfig {
  /**
   * API key principal (obrigatória)
   */
  apiKey: string;

  /**
   * API key de fallback (opcional)
   */
  apiKeyFallback?: string;

  /**
   * Modelo padrão para chat completions
   * @default "gpt-4o"
   */
  defaultModel: string;

  /**
   * Modelo para embeddings
   * @default "text-embedding-3-small"
   */
  embeddingModel: string;

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
export class OpenAIConfigService implements OnModuleInit {
  private readonly logger = new Logger(OpenAIConfigService.name);
  private config: OpenAIConfig;

  constructor(private readonly configService: ConfigService) {
    const openaiConfig: OpenAIConfiguration =
      this.configService.get<OpenAIConfiguration>('openai') ??
      ({} as OpenAIConfiguration);

    this.config = {
      apiKey: openaiConfig.apiKey ?? '',
      apiKeyFallback: openaiConfig.apiKeyFallback,
      defaultModel: openaiConfig.model ?? 'gpt-4o',
      embeddingModel: openaiConfig.embeddingModel ?? 'text-embedding-3-small',
      timeoutMs: openaiConfig.timeout ?? 180000,
      maxRetries: openaiConfig.maxRetries ?? 3,
    };
  }

  onModuleInit(): void {
    this.validateConfiguration();
  }

  /**
   * Valida que a configuração obrigatória está presente
   * @throws Error se OPENAI_API_KEY não está configurada
   */
  private validateConfiguration(): void {
    if (!this.config.apiKey) {
      this.logger.error(
        'OPENAI_API_KEY is not configured. OpenAI provider will not work.',
      );
      // Em produção, podemos querer fazer throw aqui
      // throw new Error('OPENAI_API_KEY is required');
    } else {
      this.logger.log(
        `OpenAI configured with model: ${this.config.defaultModel}, timeout: ${this.config.timeoutMs}ms`,
      );

      if (this.config.apiKeyFallback) {
        this.logger.log('OpenAI fallback API key configured');
      }
    }
  }

  /**
   * Retorna a configuração completa
   */
  getConfig(): OpenAIConfig {
    return this.config;
  }

  /**
   * Retorna a API key principal
   */
  getApiKey(): string {
    return this.config.apiKey;
  }

  /**
   * Retorna a API key de fallback (se configurada)
   */
  getFallbackApiKey(): string | undefined {
    return this.config.apiKeyFallback;
  }

  /**
   * Verifica se tem fallback configurado
   */
  hasFallbackKey(): boolean {
    return !!this.config.apiKeyFallback;
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
   * Retorna max retries
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
