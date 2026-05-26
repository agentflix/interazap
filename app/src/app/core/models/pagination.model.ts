/**
 * Metadados de paginação com indicadores opcionais de intervalo.
 *
 * Contexto: retornado na maioria dos endpoints de listagem da API.
 */
export interface PaginationMeta {
  /** Número da página atual (baseado em 1). */
  current_page: number;
  /** Índice do primeiro item da página atual (opcional). */
  from?: number;
  /** Número da última página disponível. */
  last_page: number;
  /** Quantidade de itens por página. */
  per_page: number;
  /** Índice do último item da página atual (opcional). */
  to?: number;
  /** Total de itens em todas as páginas. */
  total: number;
}

/**
 * Resposta paginada padrão da API com indicador opcional de sucesso.
 *
 * Contexto: tipo genérico usado como base para respostas de listagem
 * em todo o frontend. Extenda com `extends PaginatedResponse<T>` nos models.
 *
 * @example
 * ```typescript
 * const response: PaginatedResponse<Contact> = {
 *   success: true,
 *   data: contacts,
 *   meta: { current_page: 1, last_page: 3, per_page: 15, total: 42 }
 * };
 * ```
 */
export interface PaginatedResponse<T> {
  /** Indica se a requisição foi bem-sucedida. */
  success?: boolean;
  /** Array de itens da página atual. */
  data: T[];
  /** Metadados de paginação. */
  meta: PaginationMeta;
}
