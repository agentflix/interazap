/**
 * Models and types for sortable-list component.
 */

/** Sortable list item */
export interface AfSortableItem {
  id: string;
  label: string;
  [key: string]: unknown;
}
