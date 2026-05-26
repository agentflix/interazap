/**
 * Modelos e tipos do componente de seleção (select).
 */

/** Opção do componente select */
export interface AfSelectOption {
  /** Valor da opção */
  value: string | number;
  /** Rótulo de exibição */
  label: string;
}

export type SelectOption = AfSelectOption;
