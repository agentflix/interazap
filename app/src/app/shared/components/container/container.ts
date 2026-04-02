import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfContainerComponent — Responsive centered container.
 *
 * @example
 * ```html
 * <af-container size="lg">
 *   <h1>Page content</h1>
 * </af-container>
 * ```
 */
@Component({
  selector: 'af-container',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './container.html',
})
export class AfContainerComponent {
  /** Max width size */
  readonly size = input<'sm' | 'md' | 'lg' | 'xl' | 'full'>('lg');

  /** Horizontal padding */
  readonly padded = input(true);

  protected readonly containerClasses = computed(() => {
    const sizes: Record<string, string> = {
      sm: 'max-w-2xl',
      md: 'max-w-4xl',
      lg: 'max-w-6xl',
      xl: 'max-w-7xl',
      full: 'max-w-full',
    };
    const px = this.padded() ? 'px-4 sm:px-6 lg:px-8' : '';
    return `mx-auto w-full ${sizes[this.size()]} ${px}`;
  });
}
