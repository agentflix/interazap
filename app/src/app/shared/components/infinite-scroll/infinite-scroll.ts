import {
  type ElementRef,
  type OnInit,
  type OnDestroy,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  viewChild,
} from '@angular/core';

/**
 * Dispara o carregamento de mais itens ao rolar próximo ao fim da lista.
 *
 * @example
 * ```html
 * <af-infinite-scroll (loadMore)="fetchNextPage()" [loading]="loading()">
 *   @for (item of items(); track item.id) {
 *     <app-item-card [item]="item" />
 *   }
 * </af-infinite-scroll>
 * ```
 */
@Component({
  selector: 'af-infinite-scroll',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './infinite-scroll.html',
  host: { class: 'block' },
})
export class AfInfiniteScrollComponent implements OnInit, OnDestroy {
  /** Indica se está carregando */
  readonly loading = input(false);

  /** Distância em pixels do fim para disparar o evento */
  readonly threshold = input(100);

  /** Emitido quando mais conteúdo deve ser carregado */
  readonly loadMore = output<void>();

  private readonly sentinelRef = viewChild<ElementRef<HTMLElement>>('sentinel');
  private observer: IntersectionObserver | null = null;

  ngOnInit(): void {
    // Defer to allow template to render
    queueMicrotask(() => this.setupObserver());
  }

  ngOnDestroy(): void {
    this.observer?.disconnect();
  }

  private setupObserver(): void {
    const sentinel = this.sentinelRef()?.nativeElement;
    if (!sentinel) return;

    this.observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !this.loading()) {
          this.loadMore.emit();
        }
      },
      { rootMargin: `0px 0px ${this.threshold()}px 0px` },
    );
    this.observer.observe(sentinel);
  }
}
