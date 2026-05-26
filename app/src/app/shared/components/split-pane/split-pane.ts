import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Layout dividido em dois painéis com dimensionamento flexível.
 *
 * @example
 * ```html
 * <af-split-pane direction="horizontal" [splitRatio]="30">
 *   <div left>Sidebar</div>
 *   <div right>Principal</div>
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
  /** Direção da divisão */
  readonly direction = input<'horizontal' | 'vertical'>('horizontal');

  /** Proporção do painel esquerdo/superior em porcentagem */
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
