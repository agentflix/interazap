/**
 * Models and types for radio-input component.
 */

/** Configuration for a single radio option */
export interface AfRadioOption {
  /** Value emitted when selected */
  value: string;
  /** Display label */
  label: string;
  /** Whether this option is disabled */
  disabled?: boolean;
}
