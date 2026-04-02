/**
 * Models and types for kanban-column component.
 */

/** Kanban card */
export interface AfKanbanCard {
  id: string;
  title: string;
  description?: string;
  tag?: string;
  tagColor?: string;
}
