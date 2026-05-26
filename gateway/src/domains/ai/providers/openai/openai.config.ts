/**
 * Serviço de configuração tipada para o provider OpenAI.
 *
 * Contexto: lê as variáveis `OPENAI_API_KEY`, modelo padrão, modelo de embeddings, timeout
 * e retries da configuração NestJS e valida a presença da chave de API no `onModuleInit`.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { OpenAIConfiguration } from '../../../../core/config/configuration';

/**
 * Interface tipada com os parâmetros de configuração do provider OpenAI.
 */
export interface OpenAIConfig {
  /** API key principal (obrigatória). */
  apiKey: string;

  /** API key de fallback (opcional). */
  apiKeyFallback?: string;

  /**
   * Modelo padrão para chat completions.
   * @default "gpt-4o"
   */
  defaultModel: string;

  /**
   * Modelo utilizado para geração de embeddings.
   * @default "text-embedding-3-small"
   */
  embeddingModel: string;

  /**
   * Timeout em milissegundos.
   * @default 180000 (3 minutos)
   */
  timeoutMs: number;

  /**
   * Número máximo de tentativas de retry.
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
   * Valida que a configuração obrigatória está presente, logando erro quando ausente.
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

  /** Retorna o objeto de configuração completo do OpenAI. */
  getConfig(): OpenAIConfig {
    return this.config;
  }

  /** Retorna a API key principal do OpenAI. */
  getApiKey(): string {
    return this.config.apiKey;
  }

  /** Retorna a API key de fallback quando configurada. */
  getFallbackApiKey(): string | undefined {
    return this.config.apiKeyFallback;
  }

  /**
   * Verifica se uma chave de API de fallback está configurada.
   * @returns `true` quando a chave de fallback está presente
   */
  hasFallbackKey(): boolean {
    return !!this.config.apiKeyFallback;
  }

  /** Retorna o nome do modelo OpenAI padrão configurado. */
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
   * Verifica se o provider OpenAI está corretamente configurado.
   * @returns `true` quando a chave de API está presente
   */
  isConfigured(): boolean {
    return !!this.config.apiKey;
  }
}
