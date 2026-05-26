import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Container de rolagem com estilização de barra fina.
 *
 * @example
 * ```html
 * <af-scroll-area maxHeight="400px">
 *   <div class="space-y-2">...</div>
 * </af-scroll-area>
 *
 * <!-- Desativa a rolagem (overflow-visible) -->
 * <af-scroll-area [scrollable]="false">
 *   <div>Conteúdo que pode transbordar...</div>
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
  /** Altura máxima do container de rolagem */
  readonly maxHeight = input('300px');

  /** Indica se a rolagem está habilitada. Quando falso, usa overflow-visible sem max-height */
  readonly scrollable = input(true);

  /** Classes CSS do container baseadas no estado de rolagem */
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
