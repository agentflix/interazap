/**
 * Modelos e tipos do componente de abas.
 */

/** Definição de item de aba */
export interface AfTabItem {
  id: string;
  label: string;
  disabled?: boolean;
  icon?: string;
  badge?: string | number;
}
