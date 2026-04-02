import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfSplitPaneComponent — Two-panel split layout with flexible sizing.
 *
 * @example
 * ```html
 * <af-split-pane direction="horizontal" [splitRatio]="30">
 *   <div left>Sidebar</div>
 *   <div right>Main</div>
 * </af-split-pane>
 * ```
 */
@Component({
  selector: 'af-split-pane',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './split-pane.html',
  host: { class: 'block h-full' },
})
export class AfSplitPaneComponent {
  /** Split direction */
  readonly direction = input<'horizontal' | 'vertical'>('horizontal');

  /** Left/top pane ratio as percentage */
  readonly splitRatio = input(50);

  protected readonly wrapperClasses = computed(() => {
    const dir = this.direction() === 'horizontal' ? 'flex-row' : 'flex-col';
    return `flex ${dir} h-full`;
  });

  protected readonly dividerClasses = computed(() => {
    return this.direction() === 'horizontal'
      ? 'w-px bg-neutral-200 dark:bg-neutral-700'
      : 'h-px bg-neutral-200 dark:bg-neutral-700';
  });
}
