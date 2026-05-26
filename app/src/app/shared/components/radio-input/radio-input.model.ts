/**
 * Modelos e tipos do componente de botão de rádio.
 */

/** Configuração de uma opção de rádio individual. */
export interface AfRadioOption {
  /** Valor emitido quando selecionado */
  value: string;
  /** Rótulo de exibição */
  label: string;
  /** Indica se esta opção está desabilitada */
  disabled?: boolean;
}
