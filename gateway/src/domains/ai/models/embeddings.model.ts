/**
 * Modelos do domínio AI do gateway.
 * Agrupa os contratos de resposta do endpoint interno de embeddings.
 */

/**
 * Representa um vetor de embedding retornado pelo provider.
 */
export interface EmbeddingsResponseItem {
  /** Tipo do item retornado pelo provider. */
  object: string;
  /** Vetor numérico gerado para o conteúdo solicitado. */
  embedding: number[];
  /** Posição original do item dentro da requisição. */
  index: number;
}

/**
 * Representa o resumo de uso de tokens na geração de embeddings.
 */
export interface EmbeddingsResponseUsage {
  /** Tokens consumidos no prompt. */
  prompt_tokens: number;
  /** Total de tokens consumidos pela chamada. */
  total_tokens: number;
}

/**
 * Representa o corpo HTTP retornado pelo endpoint de embeddings.
 */
export interface EmbeddingsResponseBody {
  /** Tipo do objeto principal retornado. */
  object: string;
  /** Lista de embeddings gerados. */
  data: EmbeddingsResponseItem[];
  /** Modelo responsável pela geração dos vetores. */
  model: string;
  /** Informações opcionais de consumo de tokens. */
  usage?: EmbeddingsResponseUsage;
}
