import { Injectable, Logger, OnModuleDestroy } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import Redis, { Redis as RedisClient } from 'ioredis';
import { RedisStreamMessage } from '../models/redis.model';

export type { RedisStreamMessage };

/**
 * Serviço de conexão com o Redis para o gateway.
 *
 * Gerencia múltiplas conexões dedicadas (comandos, pub/sub e operações bloqueantes)
 * para garantir isolamento correto entre os diferentes modos de uso do Redis.
 */
@Injectable()
export class RedisService implements OnModuleDestroy {
  private readonly logger = new Logger(RedisService.name);
  private commandClient: RedisClient;
  private pubsubClient: RedisClient;
  private blockingClient: RedisClient;

  /**
   * Inicializa três conexões Redis dedicadas (comandos, pub/sub e bloqueante)
   * a partir da variável de ambiente `REDIS_URL`.
   * @param configService Serviço de configuração do NestJS
   */
  constructor(private readonly configService: ConfigService) {
    const url =
      this.configService.get<string>('REDIS_URL') ?? 'redis://localhost:6379';

    // Separate connection for commands (get, set, xadd, etc.)
    this.commandClient = new Redis(url, {
      maxRetriesPerRequest: null,
      enableReadyCheck: true,
      connectionName: 'gateway-commands',
    });

    // Separate connection for pub/sub (subscribe blocks the connection)
    this.pubsubClient = new Redis(url, {
      maxRetriesPerRequest: null,
      enableReadyCheck: true,
      connectionName: 'gateway-pubsub',
    });

    // Dedicated connection for blocking operations (BLPOP, BRPOP).
    // A client in a blocking command cannot issue other commands until the
    // blocking call returns, so it must never be shared with the command client.
    this.blockingClient = new Redis(url, {
      maxRetriesPerRequest: null,
      enableReadyCheck: true,
      connectionName: 'gateway-blocking',
    });

    this.commandClient.on('error', (err) =>
      this.logger.error('Redis command client error', err.stack),
    );
    this.commandClient.on('connect', () =>
      this.logger.log('Redis command client connected'),
    );

    this.pubsubClient.on('error', (err) =>
      this.logger.error('Redis pub/sub client error', err.stack),
    );
    this.pubsubClient.on('connect', () =>
      this.logger.log('Redis pub/sub client connected'),
    );

    this.blockingClient.on('error', (err) =>
      this.logger.error('Redis blocking client error', err.stack),
    );
    this.blockingClient.on('connect', () =>
      this.logger.log('Redis blocking client connected'),
    );
  }

  /** Encerra graciosamente os três clients Redis ao destruir o módulo. */
  async onModuleDestroy() {
    await Promise.all([
      this.commandClient.quit(),
      this.pubsubClient.quit(),
      this.blockingClient.quit(),
    ]);
  }

  /**
   * Retorna o client de comandos para operações Redis regulares.
   *
   * @returns Client Redis para get, set, xadd e outros comandos genéricos
   */
  getClient(): RedisClient {
    return this.commandClient;
  }

  /**
   * Retorna o client dedicado para operações de pub/sub.
   * Utilize este client para subscribe/psubscribe para evitar bloqueio
   * de outros comandos na mesma conexão.
   *
   * @returns Client Redis dedicado para pub/sub
   */
  getPubSubClient(): RedisClient {
    return this.pubsubClient;
  }

  /**
   * Retorna o client dedicado para operações bloqueantes (BLPOP, BRPOP).
   * Um client em operação bloqueante não pode emitir outros comandos até o retorno,
   * portanto este client nunca deve ser compartilhado com o client de comandos.
   *
   * @returns Client Redis dedicado para operações bloqueantes
   */
  getBlockingClient(): RedisClient {
    return this.blockingClient;
  }

  /**
   * Publica uma mensagem em um canal Redis Pub/Sub.
   *
   * @param channel - Nome do canal
   * @param payload - Payload a ser serializado como JSON
   * @returns Número de subscribers que receberam a mensagem
   */
  async publish(channel: string, payload: unknown): Promise<number> {
    const message = JSON.stringify(payload);
    return this.commandClient.publish(channel, message);
  }

  /**
   * Publica um evento em um Redis Stream com MAXLEN aproximado de 10.000 entradas.
   *
   * @param stream - Nome do stream
   * @param payload - Payload como objeto (values serão serializados para string)
   * @returns ID da mensagem publicada ou null em caso de falha
   */
  async publishStream(
    stream: string,
    payload: Record<string, unknown>,
  ): Promise<string | null> {
    // Redis Streams require alternating key/value strings
    const entries: string[] = [];
    for (const [key, value] of Object.entries(payload)) {
      const serialized =
        typeof value === 'string'
          ? value
          : value === undefined
            ? ''
            : JSON.stringify(value);
      entries.push(key, serialized);
    }
    // Use MAXLEN with ~ (approximate) to cap stream size at ~10000 entries
    // This prevents unbounded memory growth while allowing efficient trimming
    return this.commandClient.xadd(
      stream,
      'MAXLEN',
      '~',
      '10000',
      '*',
      ...entries,
    );
  }

  /**
   * Garante idempotência usando Redis SETNX.
   * Retorna true se a chave foi criada (primeira vez), false se já existia.
   *
   * @param key - Chave de idempotência
   * @param ttlSeconds - Tempo de vida em segundos (padrão: 300s)
   * @returns true se processamento é novo, false se duplicado
   */
  async ensureIdempotent(key: string, ttlSeconds = 300): Promise<boolean> {
    const result = await this.commandClient.set(
      key,
      '1',
      'EX',
      ttlSeconds,
      'NX',
    );
    return result === 'OK';
  }

  /**
   * Obtém o valor de uma chave Redis.
   *
   * @param key - Chave Redis
   * @returns Valor da chave ou null se não encontrada
   */
  async get(key: string): Promise<string | null> {
    return this.commandClient.get(key);
  }

  /**
   * Define o valor de uma chave Redis com TTL opcional.
   *
   * @param key - Chave Redis
   * @param value - Valor a armazenar
   * @param ttlSeconds - Tempo de vida em segundos (opcional)
   */
  async set(key: string, value: string, ttlSeconds?: number): Promise<void> {
    if (ttlSeconds && ttlSeconds > 0) {
      await this.commandClient.set(key, value, 'EX', ttlSeconds);
      return;
    }

    await this.commandClient.set(key, value);
  }

  /**
   * Remove uma chave do Redis.
   *
   * @param key - Chave a ser removida
   * @returns Número de chaves removidas (0 ou 1)
   */
  async delete(key: string): Promise<number> {
    return this.commandClient.del(key);
  }

  /**
   * Lê mensagens de um Redis Stream via XREADGROUP com bloqueio.
   *
   * @param stream - Nome do stream
   * @param group - Nome do consumer group
   * @param consumer - Nome do consumer
   * @param blockMs - Tempo máximo de bloqueio em ms (padrão: 5000ms)
   * @param count - Número máximo de mensagens a retornar (padrão: 10)
   * @returns Lista de mensagens lidas do stream
   */
  async xreadBlock(
    stream: string,
    group: string,
    consumer: string,
    blockMs: number = 5000,
    count: number = 10,
  ): Promise<RedisStreamMessage[]> {
    try {
      const result = await (
        this.commandClient as unknown as {
          xreadgroup: (...args: Array<string | number>) => Promise<unknown>;
        }
      ).xreadgroup(
        'GROUP',
        group,
        consumer,
        'COUNT',
        count,
        'BLOCK',
        blockMs,
        'STREAMS',
        stream,
        '>',
      );

      if (!result || !Array.isArray(result) || result.length === 0) {
        return [];
      }

      const streamData = result[0] as unknown[];
      if (!Array.isArray(streamData) || streamData.length < 2) {
        return [];
      }

      const messagesData = streamData[1] as Array<[string, string[]]>;
      return messagesData.map(([id, fields]) => ({
        id,
        fields: this.parseFields(fields),
      }));
    } catch (error) {
      this.logger.error('Failed to perform blocking stream read', error);
      return [];
    }
  }

  /**
   * Converte o array flat de campos do Redis Stream (alternando chave/valor)
   * em um registro estruturado de chave-valor.
   * @param fields Array alternado [chave, valor, chave, valor, ...] do XREADGROUP
   * @returns Mapa de nomes de campos para seus valores string
   */
  private parseFields(fields: string[]): Record<string, string> {
    const result: Record<string, string> = {};
    for (let i = 0; i < fields.length; i += 2) {
      result[fields[i]] = fields[i + 1];
    }
    return result;
  }
}
