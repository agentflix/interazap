import { Component, ChangeDetectionStrategy, input, computed, output } from '@angular/core';

/**
 * Primary button component for InteraZap UI Kit.
 *
 * @example
 * ```html
 * <af-button variant="primary" size="md">Save</af-button>
 * <af-button variant="ghost" size="sm">Cancel</af-button>
 * <af-button variant="danger" [disabled]="true">Delete</af-button>
 * ```
 */
@Component({
  selector: 'af-button, app-button',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '[class]': 'hostClasses()',
    '[attr.disabled]': 'disabled() || null',
  },
  templateUrl: './button.html',
})
export class AfButtonComponent {
  /** Visual style variant */
  readonly variant = input<
    'primary' | 'secondary' | 'ghost' | 'danger' | 'outline' | 'success' | 'default'
  >('primary');

  /** Button size */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('sm');

  /** Whether button is disabled */
  readonly disabled = input(false);

  /** HTML button type attribute */
  readonly type = input<'button' | 'submit' | 'reset'>('button');

  /** Full width button */
  readonly block = input(false);

  /** Legacy full-width alias. */
  readonly fullWidth = input(false);

  /** Legacy icon input for compatibility with older templates. */
  readonly icon = input<string>();

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Accessible label for screen readers */
  readonly ariaLabel = input<string>('');

  /** Legacy output alias for click */
  readonly clicked = output<MouseEvent>();

  protected readonly hostClasses = computed(() => 'inline-flex');

  protected readonly buttonClasses = computed(() => {
    const base = [
      'inline-flex items-center justify-center gap-2',
      'font-semibold rounded-sm',
      'transition-colors duration-150',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
      'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none',
      'cursor-pointer select-none',
    ];

    const sizes: Record<string, string> = {
      xs: 'px-2.5 py-1 text-xs',
      sm: 'px-3 py-1.5 text-sm',
      md: 'px-4 py-2 text-sm',
      lg: 'px-6 py-2.5 text-base',
    };

    const variants: Record<string, string> = {
      primary: [
        'bg-primary-400 text-neutral-900',
        'hover:bg-primary-500',
        'active:bg-primary-600',
        'focus-visible:ring-primary-500/50',
      ].join(' '),
      secondary: [
        'bg-primary-900 text-white',
        'hover:bg-primary-700',
        'active:bg-primary-950',
        'focus-visible:ring-primary-900/50',
      ].join(' '),
      ghost: [
        'bg-transparent text-neutral-700 dark:text-neutral-300',
        'hover:bg-neutral-100 dark:hover:bg-neutral-800',
        'active:bg-neutral-200 dark:active:bg-neutral-700',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
      danger: [
        'bg-danger text-white',
        'hover:bg-danger-600',
        'active:bg-danger-700',
        'focus-visible:ring-danger/50',
      ].join(' '),
      outline: [
        'border border-hairline-strong dark:border-neutral-600',
        'bg-transparent text-neutral-700 dark:text-neutral-300',
        'hover:bg-surface-50 dark:hover:bg-neutral-800',
        'active:bg-neutral-100 dark:active:bg-neutral-700',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
      success: [
        'bg-success text-white',
        'hover:bg-success-600',
        'active:bg-success-700',
        'focus-visible:ring-success/50',
      ].join(' '),
      default: [
        'bg-transparent text-neutral-700 dark:text-neutral-300',
        'hover:bg-neutral-100 dark:hover:bg-neutral-800',
        'active:bg-neutral-200 dark:active:bg-neutral-700',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
    };

    const blockClass = this.block() || this.fullWidth() ? 'w-full' : '';

    return [...base, sizes[this.size()], variants[this.variant()], blockClass].join(' ');
  });

  protected onClick(event: MouseEvent): void {
    this.clicked.emit(event);
  }
}

export const ButtonComponent = AfButtonComponent;
