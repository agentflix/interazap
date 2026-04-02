import { Component, ChangeDetectionStrategy, input, computed, output } from '@angular/core';
import { AfSpinnerComponent } from '../spinner/spinner';

/**
 * Button that shows a spinner when loading. Disables interaction automatically.
 *
 * @example
 * ```html
 * <af-loading-button [loading]="saving()" variant="primary" (click)="save()">
 *   Save Changes
 * </af-loading-button>
 * ```
 */
@Component({
  selector: 'af-loading-button, app-loading-button',
  standalone: true,
  imports: [AfSpinnerComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './loading-button.html',
})
export class AfLoadingButtonComponent {
  /** Visual style variant */
  readonly variant = input<'primary' | 'secondary' | 'danger' | 'success'>('primary');

  /** Button size */
  readonly size = input<'sm' | 'md' | 'lg'>('sm');

  /** Whether button is in loading state */
  readonly loading = input(false);

  /** Whether button is disabled */
  readonly disabled = input(false);

  /** HTML button type attribute */
  readonly type = input<'button' | 'submit'>('button');

  /** Full width button */
  readonly block = input(false);

  /** Legacy alias for full width button */
  readonly fullWidth = input(false);

  /** Loading text label */
  readonly loadingText = input('Carregando...');

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Legacy output alias for click */
  readonly clicked = output<MouseEvent>();

  protected readonly spinnerSize = computed(() => {
    const map: Record<string, 'xs' | 'sm' | 'md'> = { sm: 'xs', md: 'sm', lg: 'md' };
    return map[this.size()];
  });

  protected readonly buttonClasses = computed(() => {
    const base = [
      'relative inline-flex items-center justify-center gap-2',
      'font-semibold rounded-md',
      'transition-all duration-150 ease-in-out',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
      'disabled:opacity-60 disabled:cursor-not-allowed',
      'cursor-pointer select-none',
    ];

    const sizes: Record<string, string> = {
      sm: 'px-3 py-1.5 text-sm',
      md: 'px-4 py-2 text-sm',
      lg: 'px-6 py-2.5 text-base',
    };

    const variants: Record<string, string> = {
      primary: 'bg-accent-500 text-white hover:bg-accent-600 focus-visible:ring-accent-500/50',
      secondary: 'bg-primary-900 text-white hover:bg-primary-700 focus-visible:ring-primary-900/50',
      danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600/50',
      success: 'bg-success text-white hover:bg-success/90 focus-visible:ring-success/50',
    };

    const blockClass = this.block() || this.fullWidth() ? 'w-full' : '';

    return [...base, sizes[this.size()], variants[this.variant()], blockClass]
      .filter(Boolean)
      .join(' ');
  });

  protected onClick(event: MouseEvent): void {
    this.clicked.emit(event);
  }
}

export const LoadingButtonComponent = AfLoadingButtonComponent;
