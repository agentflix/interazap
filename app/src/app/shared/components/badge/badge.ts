import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Small label or tag for categorization and status display.
 *
 * @example
 * ```html
 * <af-badge variant="success">Active</af-badge>
 * <af-badge variant="warning" size="sm">Pending</af-badge>
 * <af-badge variant="info" dot>New</af-badge>
 * ```
 */
@Component({
  selector: 'af-badge',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './badge.html',
})
export class AfBadgeComponent {
  /** Color variant */
  readonly variant = input<'default' | 'success' | 'warning' | 'danger' | 'info'>('default');

  /** Badge size */
  readonly size = input<'sm' | 'md'>('md');

  /** Show a dot indicator before the text */
  readonly dot = input(false);

  protected readonly badgeClasses = computed(() => {
    const base = 'inline-flex items-center gap-1.5 font-medium rounded-full whitespace-nowrap';

    const sizes: Record<string, string> = {
      sm: 'px-2 py-0.5 text-xs',
      md: 'px-2.5 py-1 text-xs',
    };

    const variants: Record<string, string> = {
      default: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
      success: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
      warning: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
      danger: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400',
      info: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    };

    return `${base} ${sizes[this.size()]} ${variants[this.variant()]}`;
  });

  protected readonly dotClasses = computed(() => {
    const base = 'size-1.5 rounded-full';

    const variants: Record<string, string> = {
      default: 'bg-neutral-500',
      success: 'bg-emerald-500',
      warning: 'bg-amber-500',
      danger: 'bg-red-500',
      info: 'bg-sky-500',
    };

    return `${base} ${variants[this.variant()]}`;
  });
}
