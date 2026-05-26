/**
 * Modelos de mensagens para operações com Redis Streams.
 *
 * Contexto: módulo infra/models. Define os tipos de entrada e handler
 * utilizados pelos consumers de stream do gateway.
 */

import type { GatewayMessage } from '../../common/models/gateway-message.model';

/**
 * Mensagem parseada de uma entrada do Redis Stream.
 * Combina o ID da entrada com o payload tipado da mensagem de gateway.
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
