/**
 * Modelos e tipos do componente de coluna kanban.
 */

/** Card do quadro kanban. */
export interface AfKanbanCard {
  id: string;
  title: string;
  description?: string;
  tag?: string;
  tagColor?: string;
}
