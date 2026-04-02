/**
 * Modelos de mensagens para operação com Redis Streams.
 */

import type { GatewayMessage } from '../../common/models/gateway-message.model';

/** Mensagem parseada de uma entrada do Redis Stream. */
export interface StreamMessage<T = unknown> {
  id: string;
  message: GatewayMessage<T>;
}

/** Função handler para processar uma mensagem do stream. */
export type StreamMessageHandler<T = unknown> = (
  message: StreamMessage<T>,
) => Promise<void>;
