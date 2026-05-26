/**
 * Representa uma resposta rápida (atalho de mensagem) pré-cadastrada.
 *
 * Contexto: usado no módulo de Chat para permitir que agentes enviem
 * mensagens padronizadas com rapidez. Acessível via `/` no editor de mensagens.
 */
export interface QuickAnswer {
  id: string;
  /** Nome/título da resposta rápida para identificação. */
  name: string;
  /** Conteúdo textual da resposta que será enviada. */
  content: string;
  /** Atalho de teclado para acesso rápido (ex: /saudacao). */
  shortcut?: string;
  is_active: boolean;
  company_id?: string;
  created_at?: string;
  updated_at?: string;
}

/**
 * Filtros para listagem de respostas rápidas.
 */
export interface QuickAnswerFilters {
  search?: string;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  category?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
}
