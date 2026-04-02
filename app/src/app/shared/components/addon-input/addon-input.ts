import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Input group with a leading addon (button or icon) on the left.
 *
 * @description Renders a text input with a clickable button addon on the
 * left side. Ideal for "Add" actions, prefix selectors, or icon-triggered inputs.
 *
 * @example
 * ```html
 * <af-addon-input
 *   [control]="tagControl"
 *   label="Tag"
 *   addonIcon="plus"
 *   addonLabel="Add"
 *   placeholder="Nova tag..."
 *   (addonClick)="onAddTag()"
 * />
 * ```
 */
@Component({
  selector: 'af-addon-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './addon-input.html',
})
export class AfAddonInputComponent {
  /** FormControl for the input */
  readonly control = input.required<FormControl<string>>();

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string>('mb-4');

  /** Input type */
  readonly type = input<string>('text');

  /** Placeholder text */
  readonly placeholder = input('');

  /** Required asterisk on label */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Campo obrigatório.');

  /** Lucide icon name for the addon button */
  readonly addonIcon = input<string>();

  /** Text label for the addon button */
  readonly addonLabel = input<string>();

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Emitted when addon button is clicked */
  readonly addonClick = output<void>();

  /** Unique ID */
  protected readonly inputId = `addon-${Math.random().toString(36).slice(2, 9)}`;

  /** Addon button styling */
  protected readonly addonClasses = computed(() =>
    [
      'inline-flex items-center gap-1.5 px-3',
      'bg-neutral-100 dark:bg-neutral-800',
      'border border-r-0 border-neutral-300 dark:border-neutral-600',
      'rounded-l-md text-sm font-medium',
      'text-neutral-600 dark:text-neutral-300',
      'hover:bg-neutral-200 dark:hover:bg-neutral-700',
      'transition-colors duration-150 cursor-pointer',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/30',
    ].join(' '),
  );

  /** Input styling */
  protected readonly inputClasses = computed(() => {
    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    return [
      'flex-1 h-10 px-3 text-sm',
      'bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'border rounded-r-md',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'transition-colors duration-150',
      borderColor,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);
}
