/**
 * Modelos e tipos do componente de menu dropdown.
 */

/** Item do menu dropdown. */
export interface AfDropdownMenuItem {
  label: string;
  value: string;
  icon?: string;
  destructive?: boolean;
  disabled?: boolean;
  divider?: boolean;
}
