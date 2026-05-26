import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { IdempotencyOptions } from '../../models/idempotency.model';

export type { IdempotencyOptions };

/**
 * Representa o resultado de uma verificação de idempotência.
 */
export interface IdempotencyResult<T = unknown> {
  /** Se a requisição é uma duplicata */
  isDuplicate: boolean;
  /** Resultado cacheado, se for duplicata */
  cachedResult?: T;
  /** Chave utilizada para esta requisição */
  key: string;
}

/**
 * Serviço para gerenciamento de operações idempotentes.
 *
 * Evita o processamento duplicado de webhooks e requisições de API
 * armazenando resultados em cache indexados por identificadores únicos.
 */
@Injectable()
export class IdempotencyService {
  private readonly logger = new Logger(IdempotencyService.name);
  private readonly defaultTtl = 86400; // 24 hours
  private readonly defaultPrefix = 'idempotent';

  /**
   * Inicializa o serviço de idempotência com uma conexão Redis para armazenamento em cache.
   *
   * @param redis - RedisService utilizado para operações SET/GET/DEL nas chaves de idempotência
   */
  constructor(private readonly redis: RedisService) {}

  /**
   * Verifica se uma operação já foi processada anteriormente.
   *
   * @param key - Identificador único da operação
   * @param options - Opções de configuração
   * @returns Resultado da verificação de idempotência
   */
  async check<T>(
    key: string,
    options: IdempotencyOptions = {},
  ): Promise<IdempotencyResult<T>> {
    const cacheKey = this.buildKey(key, options.prefix);

    try {
      const cached = await this.redis.get(cacheKey);

      if (cached) {
        this.logger.debug(`Idempotency hit for key: ${key}`);
        return {
          isDuplicate: true,
          cachedResult: JSON.parse(cached) as T,
          key: cacheKey,
        };
      }

      return {
        isDuplicate: false,
        key: cacheKey,
      };
    } catch (error) {
      this.logger.error(`Idempotency check failed: ${error}`);
      // On error, allow processing to continue
      return {
        isDuplicate: false,
        key: cacheKey,
      };
    }
  }

  /**
   * Marca uma operação como processada e armazena o resultado em cache.
   *
   * @param key - Identificador único da operação
   * @param result - Resultado a ser armazenado em cache
   * @param options - Opções de configuração
   */
  async markProcessed<T>(
    key: string,
    result: T,
    options: IdempotencyOptions = {},
  ): Promise<void> {
    const cacheKey = this.buildKey(key, options.prefix);
    const ttl = options.ttlSeconds || this.defaultTtl;

    try {
      await this.redis.set(cacheKey, JSON.stringify(result), ttl);
      this.logger.debug(`Idempotency marked: ${key}, TTL: ${ttl}s`);
    } catch (error) {
      this.logger.error(`Failed to mark idempotency: ${error}`);
      // Don't throw - processing should continue even if caching fails
    }
  }

  /**
   * Executa uma função com proteção de idempotência.
   *
   * @param key - Identificador único da operação
   * @param fn - Função a ser executada
   * @param options - Opções de configuração
   * @returns Resultado da função ou resultado do cache
   */
  async execute<T>(
    key: string,
    fn: () => Promise<T>,
    options: IdempotencyOptions = {},
  ): Promise<T> {
    const checkResult = await this.check<T>(key, options);

    if (checkResult.isDuplicate && checkResult.cachedResult !== undefined) {
      return checkResult.cachedResult;
    }

    const result = await fn();
    await this.markProcessed(key, result, options);

    return result;
  }

  /**
   * Remove uma chave de idempotência (para retentativas ou testes).
   *
   * @param key - Chave a ser removida
   * @param prefix - Prefixo opcional
   */
  async clear(key: string, prefix?: string): Promise<void> {
    const cacheKey = this.buildKey(key, prefix);
    await this.redis.delete(cacheKey);
    this.logger.debug(`Idempotency cleared: ${key}`);
  }

  /**
   * Constrói a chave de cache completa com prefixo.
   *
   * @param key - Identificador da operação
   * @param prefix - Prefixo opcional
   * @returns Chave formatada `prefixo:chave`
   */
  private buildKey(key: string, prefix?: string): string {
    return `${prefix || this.defaultPrefix}:${key}`;
  }

  /**
   * Gera uma chave de idempotência para um evento de webhook.
   *
   * @param provider - Provedor do webhook (ex: 'uazapi', 'asaas')
   * @param eventId - Identificador único do evento fornecido pelo provedor
   * @returns Chave de idempotência formatada
   */
  static webhookKey(provider: string, eventId: string): string {
    return `webhook:${provider}:${eventId}`;
  }

  /**
   * Gera uma chave de idempotência para uma mensagem.
   *
   * @param ticketId - Identificador do ticket
   * @param messageHash - Hash do conteúdo da mensagem
   * @returns Chave de idempotência formatada
   */
  static messageKey(ticketId: string, messageHash: string): string {
    return `message:${ticketId}:${messageHash}`;
  }

  /**
   * Gera uma chave de idempotência para um pagamento.
   *
   * @param tenantId - Identificador do tenant
   * @param paymentId - Identificador do pagamento
   * @returns Chave de idempotência formatada
   */
  static paymentKey(tenantId: string, paymentId: string): string {
    return `payment:${tenantId}:${paymentId}`;
  }
}
