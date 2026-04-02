/**
 * Models and types for agenda-filter-bar component.
 */

export type AgendaViewMode = 'list' | 'calendar';

export interface AgendaActiveFilterChip {
  key: string;
  label: string;
}
