/**
 * Models and types for select-input component.
 */

/** Option for the select input */
export interface AfSelectOption {
  /** Option value */
  value: string | number;
  /** Display label */
  label: string;
}

export type SelectOption = AfSelectOption;
