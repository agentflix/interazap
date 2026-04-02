/**
 * Modelos do domínio AI do gateway.
 * Centraliza contratos auxiliares usados na execução de tools via Redis.
 */

/**
 * Define o contrato mínimo do cliente Redis com suporte a bloqueio por lista.
 */
export interface RedisBlockingListClient {
  /**
   * Aguarda um item em uma lista Redis por um tempo determinado.
   */
  blpop(key: string, timeoutSeconds: number): Promise<[string, string] | null>;
}
