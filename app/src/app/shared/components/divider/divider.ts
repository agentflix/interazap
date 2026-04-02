import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Visual separator line — horizontal or vertical.
 *
 * @example
 * ```html
 * <af-divider />
 * <af-divider orientation="vertical" class="h-6" />
 * <af-divider label="OR" />
 * ```
 */
@Component({
  selector: 'af-divider',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './divider.html',
})
export class AfDividerComponent {
  /** Divider orientation */
  readonly orientation = input<'horizontal' | 'vertical'>('horizontal');

  /** Optional centered label text */
  readonly label = input<string | null>(null);

  protected readonly lineClasses = computed(() => {
    if (this.orientation() === 'vertical') {
      return 'flex-1 w-px bg-neutral-200 dark:bg-neutral-700';
    }
    return 'flex-1 h-px bg-neutral-200 dark:bg-neutral-700';
  });

  protected readonly simpleLineClasses = computed(() => {
    if (this.orientation() === 'vertical') {
      return 'w-px h-full bg-neutral-200 dark:bg-neutral-700';
    }
    return 'w-full h-px bg-neutral-200 dark:bg-neutral-700';
  });
}
