import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Linha separadora visual — horizontal ou vertical.
 *
 * @example
 * ```html
 * <af-divider />
 * <af-divider orientation="vertical" class="h-6" />
 * <af-divider label="OU" />
 * ```
 */
@Component({
  selector: 'af-divider',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './divider.html',
})
export class AfDividerComponent {
  /** Orientação do separador */
  readonly orientation = input<'horizontal' | 'vertical'>('horizontal');

  /** Texto de rótulo centralizado opcional */
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
