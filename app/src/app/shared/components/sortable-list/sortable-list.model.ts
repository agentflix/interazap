/**
 * Modelos e tipos do componente de lista ordenável.
 */

/** Item da lista ordenável */
export interface AfSortableItem {
  id: string;
  label: string;
  [key: string]: unknown;
}
