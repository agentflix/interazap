/**
 * Modelos e tipos do componente de paleta de comandos.
 */

/** Item da paleta de comandos. */
export interface AfCommandItem {
  id: string;
  label: string;
  description?: string;
  icon?: string;
  group?: string;
}
