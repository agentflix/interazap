/**
 * Modelos do domínio AI do gateway.
 * Define snapshots reutilizados pelo resolvedor de prompts.
 */

/**
 * Representa um snapshot hidratado de prompt do tenant.
 */
export interface PromptSnapshot {
  /** Prompt já montado e pronto para uso. */
  prompt?: string;
  /** Data de hidratação no formato legado. */
  hydrated_at?: string;
  /** Data de hidratação no formato camelCase. */
  hydratedAt?: string;
}
