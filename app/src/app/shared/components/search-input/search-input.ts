import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Search input with magnifying glass icon on the left.
 *
 * @description Input field with a leading search icon and optional clear button.
 * Ideal for filter bars, toolbars and header searches.
 *
 * @example
 * ```html
 * <af-search-input
 *   [control]="searchControl"
 *   placeholder="Buscar contatos..."
 * />
 * ```
 */
@Component({
  selector: 'af-search-input, app-search-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './search-input.html',
})
export class AfSearchInputComponent {
  /** FormControl for the search value */
  readonly control = input.required<FormControl<string>>();

  /** Label text */
  readonly label = input<string>();

  /** Placeholder text */
  readonly placeholder = input('Buscar...');

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the input */
  readonly helpText = input<string>();

  /** Input size */
  readonly size = input<'sm' | 'md'>('sm');

  /** data-test attribute for E2E tests */
  readonly dataTest = input<string>();

  /** aria-label for accessibility */
  readonly ariaLabel = input<string>();

  /** Unique ID */
  protected readonly inputId = `search-${Math.random().toString(36).slice(2, 9)}`;

  /** Icon size based on input size */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Icon size based on input size */
  protected readonly iconSize = computed(() => (this.size() === 'sm' ? 16 : 18));

  /** Dynamic CSS classes */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 pl-9 pr-8 text-xs' : 'h-10 pl-10 pr-9 text-sm';

    return [
      'w-full rounded-md border bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'border-neutral-300 dark:border-neutral-600',
      sizeClasses,
    ].join(' ');
  });

  /** Clear the search value */
  protected clear(): void {
    this.control().setValue('');
    this.control().markAsTouched();
  }
}

export const SearchInputComponent = AfSearchInputComponent;
