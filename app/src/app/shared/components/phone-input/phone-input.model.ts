/**
 * Models and types for phone-input component.
 */

/** Available sizes for the input */
export type AfInputSize = 'sm' | 'md';

/** Country code entry */
export interface AfCountryCode {
  /** Country name */
  name: string;
  /** ISO dial code (e.g. +55) */
  code: string;
  /** ISO 2-letter country code */
  iso: string;
  /** Flag emoji */
  flag: string;
}

export type Country = AfCountryCode;
