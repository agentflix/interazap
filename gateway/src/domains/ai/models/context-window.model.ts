/**
 * Modelos do domínio AI do gateway.
 * Define snapshots reutilizados pelo resolvedor de contexto.
 */

/**
 * Representa um snapshot hidratado de contexto de ticket.
 */
export interface ContextSnapshot {
  /** Contexto serializado previamente para reutilização. */
  context?: Record<string, unknown>;
  /** Data de hidratação no formato legado. */
  hydrated_at?: string;
  /** Data de hidratação no formato camelCase. */
  hydratedAt?: string;
}
