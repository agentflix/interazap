/**
 * Modelos e tipos do componente de linha do tempo.
 */

/** Definição de entrada da linha do tempo */
export interface AfTimelineEntry {
  title: string;
  description?: string;
  timestamp: string;
  icon?: string;
  variant?: 'default' | 'success' | 'danger' | 'warning' | 'info';
}
