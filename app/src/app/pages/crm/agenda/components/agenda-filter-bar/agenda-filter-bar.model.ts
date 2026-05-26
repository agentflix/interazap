/**
 * Modelos e tipos para o componente de barra de filtros da agenda.
 */

export type AgendaViewMode = 'list' | 'calendar';

export interface AgendaActiveFilterChip {
  key: string;
  label: string;
}
