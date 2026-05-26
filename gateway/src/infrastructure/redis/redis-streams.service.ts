/**
 * Serviço de abstração para operações com Redis Streams.
 *
 * Contexto: módulo infra/redis. Encapsula XREAD, XREADGROUP e XADD,
 * fornecendo métodos tipados para publicação de GatewayMessage/GatewayResponse
 * e consumo via consumer groups com ACK.
 */

import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from './redis.service';
import {
  GatewayMessage,
  createGatewayMessage,
} from '../../common/interfaces/gateway-message.interface';
import {
  GatewayResponse,
  createSuccessResponse,
  createErrorResponse,
  createGatewayError,
} from '../../common/interfaces/gateway-response.interface';

/**
 * Mensagem parseada de uma entrada do Redis Stream.
 * Combina o ID da entrada com o payload tipado da GatewayMessage.
 */
export interface StreamMessage<T = unknown> {
  /** ID da mensagem no stream (ex: "1234567890-0"). */
  id: string;
  /** Payload deserializado como GatewayMessage. */
  message: GatewayMessage<T>;
}

/**
 * Função handler assíncrona para processar uma mensagem do stream.
 * Deve lançar erro em caso de falha para acionar o mecanismo de DLQ.
 */
export type StreamMessageHandler<T = unknown> = (
  message: StreamMessage<T>,
) => Promise<void>;

/**
 * Serviço de streams Redis tipado para o gateway.
 *
 * Contexto: módulo infra/redis. Abstrai XADD (publish), XREADGROUP (readGroup),
 * XACK (ack) e criação de consumer groups, convertendo os dados flat do Redis
 * para GatewayMessage e GatewayResponse fortemente tipados.
 */
@Injectable()
export class RedisStreamsService {
  private readonly logger = new Logger(RedisStreamsService.name);

  constructor(private readonly redisService: RedisService) {}

  /**
   * Publica uma GatewayMessage em um Redis Stream via XADD.
   * @param stream Nome do stream (ex: `ai.run.request`)
   * @param message Mensagem tipada a ser publicada
   * @returns ID da entrada publicada no stream ou null em caso de falha
   */
  async publish<T>(
    stream: string,
    message: GatewayMessage<T>,
  ): Promise<string | null> {
    const payload: Record<string, unknown> = {
      correlation_id: message.correlationId,
      timestamp: message.timestamp,
      domain: message.domain,
      action: message.action,
      provider: message.provider,
      payload: JSON.stringify(message.payload),
      metadata: message.metadata ? JSON.stringify(message.metadata) : '',
    };

    this.logger.debug(
      `Publishing to ${stream} [${message.correlationId}]`,
      message.action,
    );

    return this.redisService.publishStream(stream, payload);
  }

  /**
   * Publica uma GatewayResponse em um Redis Stream via XADD.
   * @param stream Nome do stream (ex: `ai.run.response:{correlationId}`)
   * @param response Resposta tipada a ser publicada
   * @returns ID da entrada publicada no stream ou null em caso de falha
   */
  async publishResponse<T>(
    stream: string,
    response: GatewayResponse<T>,
  ): Promise<string | null> {
    const payload: Record<string, unknown> = {
      correlation_id: response.correlationId,
      timestamp: response.timestamp,
      success: response.success,
      data: response.data ? JSON.stringify(response.data) : '',
      error: response.error ? JSON.stringify(response.error) : '',
      processing_time_ms: response.processingTimeMs ?? 0,
    };

    this.logger.debug(
      `Publishing response to ${stream} [${response.correlationId}] success=${response.success}`,
    );

    return this.redisService.publishStream(stream, payload);
  }

  /**
   * Garante que um consumer group existe para o stream informado.
   * Usa XGROUP CREATE com MKSTREAM; ignora o erro BUSYGROUP se o grupo já existir.
   * @param stream Nome do stream
   * @param group Nome do consumer group
   */
  async ensureConsumerGroup(stream: string, group: string): Promise<void> {
    try {
      const client = this.redisService.getClient();
      await client.xgroup('CREATE', stream, group, '0', 'MKSTREAM');
      this.logger.log(
        `Consumer group '${group}' created for stream '${stream}'`,
      );
    } catch (error) {
      if ((error as Error).message?.includes('BUSYGROUP')) {
        this.logger.debug(
          `Consumer group '${group}' already exists for '${stream}'`,
        );
        return;
      }
      throw error;
    }
  }

  /**
   * Lê mensagens de um stream via XREADGROUP com bloqueio.
   * @param stream Nome do stream
   * @param group Nome do consumer group
   * @param consumer Nome do consumer (único por instância)
   * @param count Número máximo de mensagens a retornar (padrão: 10)
   * @param blockMs Timeout de bloqueio em milissegundos (padrão: 2000)
   * @returns Array de mensagens tipadas parseadas do stream
   */
  async readGroup<T>(
    stream: string,
    group: string,
    consumer: string,
    count = 10,
    blockMs = 2000,
  ): Promise<StreamMessage<T>[]> {
    const client = this.redisService.getClient();

    try {
      const result = await client.xreadgroup(
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

      const streamData = result[0];
      if (!Array.isArray(streamData) || streamData.length < 2) {
        return [];
      }

      const messagesData = streamData[1] as Array<[string, string[]]>;
      return messagesData.map(([id, fields]) => ({
        id,
        message: this.parseMessage<T>(fields),
      }));
    } catch (error) {
      this.logger.error(
        `Failed to read from stream '${stream}' group '${group}'`,
        (error as Error).stack,
      );
      return [];
    }
  }

  /**
   * Confirma que uma mensagem foi processada com sucesso via XACK.
   * @param stream Nome do stream
   * @param group Nome do consumer group
   * @param messageId ID da mensagem a ser confirmada
   */
  async ack(stream: string, group: string, messageId: string): Promise<void> {
    const client = this.redisService.getClient();
    await client.xack(stream, group, messageId);
    this.logger.debug(`Acknowledged message ${messageId} from ${stream}`);
  }

  /**
   * Converte o array flat de campos do Redis Stream em uma GatewayMessage tipada.
   * Suporta payload como JSON stringificado ou como campos individuais.
   * @param fields Array alternado de chaves e valores do Redis Stream
   * @returns GatewayMessage com payload e metadata parseados
   */
  private parseMessage<T>(fields: string[]): GatewayMessage<T> {
    const fieldMap: Record<string, string> = {};
    for (let i = 0; i < fields.length; i += 2) {
      fieldMap[fields[i]] = fields[i + 1];
    }

    let payload: T;
    try {
      if (fieldMap.payload) {
        payload = JSON.parse(fieldMap.payload) as T;
      } else {
        const cloned: Record<string, unknown> = { ...fieldMap };
        delete cloned.correlation_id;
        delete cloned.correlationId;
        delete cloned.timestamp;
        delete cloned.domain;
        delete cloned.action;
        delete cloned.provider;
        delete cloned.metadata;
        payload = cloned as T;
      }
    } catch {
      payload = {} as T;
    }

    let metadata: Record<string, unknown> | undefined;
    if (fieldMap.metadata) {
      try {
        metadata = JSON.parse(fieldMap.metadata) as Record<string, unknown>;
      } catch {
        metadata = undefined;
      }
    }

    return createGatewayMessage<T>({
      correlationId:
        fieldMap.correlation_id ??
        fieldMap.correlationId ??
        fieldMap.run_id ??
        `${Date.now()}-${Math.random()}`,
      timestamp: fieldMap.timestamp ?? new Date().toISOString(),
      domain: (fieldMap.domain ?? 'ai') as GatewayMessage['domain'],
      action: fieldMap.action ?? fieldMap.event ?? '',
      provider: fieldMap.provider ?? '',
      payload,
      metadata,
    });
  }

  /**
   * Cria uma GatewayResponse de sucesso com os dados fornecidos.
   * @param correlationId ID de correlação da requisição original
   * @param data Dados do resultado
   * @param processingTimeMs Tempo de processamento em milissegundos (opcional)
   * @returns GatewayResponse tipada com success=true
   */
  createSuccessResponse<T>(
    correlationId: string,
    data: T,
    processingTimeMs?: number,
  ): GatewayResponse<T> {
    return createSuccessResponse(correlationId, data, processingTimeMs);
  }

  /**
   * Cria uma GatewayResponse de erro com o código e mensagem informados.
   * @param correlationId ID de correlação da requisição original
   * @param code Código de erro tipado pelo GatewayError
   * @param message Mensagem descritiva do erro
   * @param processingTimeMs Tempo de processamento em milissegundos (opcional)
   * @param details Detalhes adicionais do erro (opcional)
   * @returns GatewayResponse tipada com success=false
   */
  createErrorResponse(
    correlationId: string,
    code: Parameters<typeof createGatewayError>[0],
    message: string,
    processingTimeMs?: number,
    details?: unknown,
  ): GatewayResponse<never> {
    return createErrorResponse(
      correlationId,
      createGatewayError(code, message, details),
      processingTimeMs,
    );
  }
}
