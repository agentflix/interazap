/**
 * Modelos de infraestrutura Redis do gateway.
 * Define interfaces utilizadas pelo serviço de conexão com o Redis.
 */

/**
 * Representa uma mensagem lida de um Redis Stream via XRANGE/XREAD.
 */
export interface RedisStreamMessage {
  /** ID da mensagem no stream (ex: "1234567890-0") */
  id: string;
  /** Campos da mensagem como pares chave-valor string */
  fields: Record<string, string>;
}
