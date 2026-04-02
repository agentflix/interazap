import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Animated spinner indicator for loading states.
 *
 * @example
 * ```html
 * <af-spinner size="sm" />
 * <af-spinner size="lg" color="white" />
 * ```
 */
@Component({
  selector: 'af-spinner',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './spinner.html',
})
export class AfSpinnerComponent {
  /** Spinner diameter */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  /** Color variant */
  readonly color = input<'default' | 'white' | 'accent'>('default');

  protected readonly svgClasses = computed(() => {
    const sizes: Record<string, string> = {
      xs: 'size-3',
      sm: 'size-4',
      md: 'size-5',
      lg: 'size-8',
    };

    const colors: Record<string, string> = {
      default: 'text-accent-500',
      white: 'text-white',
      accent: 'text-accent-400',
    };

    return `animate-spin ${sizes[this.size()]} ${colors[this.color()]}`;
  });
}
