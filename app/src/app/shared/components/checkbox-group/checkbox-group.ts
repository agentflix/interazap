import { Component, ChangeDetectionStrategy, input, effect } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';

import type { AfCheckboxOption } from './checkbox-group.model';
export * from './checkbox-group.model';



/**
 * Checkbox group component for multi-select with array of values.
 *
 * @description Renders multiple checkboxes where each selection adds/removes
 * the value from the FormControl array.
 *
 * @example
 * ```html
 * <af-checkbox-group
 *   [control]="form.controls.roles"
 *   [options]="roleOptions"
 *   label="Perfis"
 * />
 * ```
 */
@Component({
  selector: 'af-checkbox-group, app-checkbox-group',
  standalone: true,
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './checkbox-group.html',
})
export class AfCheckboxGroupComponent {
  /** FormControl containing array of selected values */
  readonly control = input.required<FormControl<string[]>>();

  /** Checkbox options */
  readonly options = input.required<readonly AfCheckboxOption[]>();

  /** data-test prefix for each checkbox */
  readonly dataTest = input<string>('checkbox-group');

  /** Group label */
  readonly label = input<string>();

  /** Check if a value is selected */
  protected isSelected(value: string): boolean {
    const selectedValues = this.control().value ?? [];
    return selectedValues.includes(value);
  }

  /** Toggle a value in the array */
  protected toggle(value: string, event: Event): void {
    event.stopPropagation();
    this.setValue(value);
  }

  /** Toggle via Space key — prevents page scroll */
  protected toggleSpace(value: string): void {
    this.setValue(value);
  }

  private setValue(value: string): void {
    const current = this.control().value ?? [];
    if (current.includes(value)) {
      this.control().setValue(current.filter((v) => v !== value));
    } else {
      this.control().setValue([...current, value]);
    }
    this.control().markAsTouched();
  }

  /** Dynamic box classes based on checked state */
  protected boxClasses(value: string): string {
    const isChecked = this.isSelected(value);
    const base = [
      'mt-0.5 size-4 shrink-0 rounded border cursor-pointer',
      'flex items-center justify-center',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:ring-offset-0',
    ];
    const state = isChecked
      ? 'bg-accent-500 border-accent-500'
      : 'bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-600';
    return [...base, state].join(' ');
  }
}
