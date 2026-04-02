/**
 * Models and types for dropdown-menu component.
 */

/** Dropdown menu item */
export interface AfDropdownMenuItem {
  label: string;
  value: string;
  icon?: string;
  destructive?: boolean;
  disabled?: boolean;
  divider?: boolean;
}
