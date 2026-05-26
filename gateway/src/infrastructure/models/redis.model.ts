/**
 * Modelos de infraestrutura Redis do gateway.
 *
 * Contexto: módulo infra/models. Define interfaces utilizadas pelo RedisService
 * para retornar mensagens lidas via XRANGE/XREADGROUP.
 */

/**
 * Representa uma mensagem lida de um Redis Stream via XRANGE/XREADGROUP.
 */
export interface RedisStreamMessage {
  /** ID da mensagem no stream (ex: "1234567890-0"). */
  id: string;
  /** Campos da mensagem como pares chave-valor string, já parseados do formato flat. */
  fields: Record<string, string>;
}
