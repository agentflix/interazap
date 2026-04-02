/**
 * Models and types for command-palette component.
 */

/** Command palette item */
export interface AfCommandItem {
  id: string;
  label: string;
  description?: string;
  icon?: string;
  group?: string;
}
