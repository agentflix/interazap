/**
 * Models and types for tabs component.
 */

/** Tab item definition */
export interface AfTabItem {
  id: string;
  label: string;
  disabled?: boolean;
  icon?: string;
  badge?: string | number;
}
