import {
  Component,
  ChangeDetectionStrategy,
  input,
  signal,
  computed,
  OnInit,
  OnDestroy,
  ElementRef,
  viewChild,
} from '@angular/core';

/**
 * Lista virtualizada que renderiza apenas os itens visíveis.
 *
 * @example
 * ```html
 * <af-virtual-scroll [itemCount]="10000" [itemHeight]="48" [containerHeight]="400">
 *   <ng-template #itemTemplate let-index>
 *     <div class="px-4 py-3">Item {{ index }}</div>
 *   </ng-template>
 * </af-virtual-scroll>
 * ```
 */
@Component({
  selector: 'af-virtual-scroll',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './virtual-scroll.html',
  host: { class: 'block' },
})
export class AfVirtualScrollComponent {
  /** Número total de itens */
  readonly itemCount = input(0);

  /** Altura de cada item em px */
  readonly itemHeight = input(48);

  /** Altura do container em px */
  readonly containerHeight = input(400);

  /** Itens de buffer acima/abaixo da viewport */
  readonly bufferSize = input(5);

  protected readonly scrollTop = signal(0);

  protected readonly totalHeight = computed(() => this.itemCount() * this.itemHeight());

  protected readonly startIndex = computed(() => {
    const idx = Math.floor(this.scrollTop() / this.itemHeight()) - this.bufferSize();
    return Math.max(0, idx);
  });

  protected readonly endIndex = computed(() => {
    const visibleCount = Math.ceil(this.containerHeight() / this.itemHeight());
    const idx = Math.floor(this.scrollTop() / this.itemHeight()) + visibleCount + this.bufferSize();
    return Math.min(this.itemCount(), idx);
  });

  protected readonly visibleIndices = computed(() => {
    const indices: number[] = [];
    for (let i = this.startIndex(); i < this.endIndex(); i++) {
      indices.push(i);
    }
    return indices;
  });

  protected readonly offsetY = computed(() => this.startIndex() * this.itemHeight());

  protected onScroll(event: Event): void {
    const el = event.target as HTMLElement;
    this.scrollTop.set(el.scrollTop);
  }
}
