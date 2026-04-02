import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfScrollAreaComponent — Custom scrollable container with thin scrollbar styling.
 *
 * @example
 * ```html
 * <af-scroll-area maxHeight="400px">
 *   <div class="space-y-2">...</div>
 * </af-scroll-area>
 *
 * <!-- Disable scrolling (overflow-visible) -->
 * <af-scroll-area [scrollable]="false">
 *   <div>Content that can overflow...</div>
 * </af-scroll-area>
 * ```
 */
@Component({
  selector: 'af-scroll-area',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './scroll-area.html',
  styleUrl: './scroll-area.scss',
})
export class AfScrollAreaComponent {
  /** Max height of the scroll container */
  readonly maxHeight = input('300px');

  /** Whether scrolling is enabled. When false, uses overflow-visible and no max-height */
  readonly scrollable = input(true);

  /** Container CSS classes based on scrollable state */
  protected readonly containerClasses = computed(() => {
    if (!this.scrollable()) {
      return 'overflow-visible';
    }

    return [
      'af-scroll-area--scrollable',
      'overflow-y-auto scrollbar-thin scrollbar-thumb-neutral-300 dark:scrollbar-thumb-neutral-600',
      'scrollbar-track-transparent hover:scrollbar-thumb-neutral-400',
      'dark:hover:scrollbar-thumb-neutral-500',
    ].join(' ');
  });
}
