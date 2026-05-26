import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Campo ou exibição de avaliação por estrelas.
 *
 * @example
 * ```html
 * <af-rating [value]="3" [max]="5" (valueChange)="onRate($event)" />
 * ```
 */
@Component({
  selector: 'af-rating',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './rating.html',
})
export class AfRatingComponent {
  /** Valor atual da avaliação */
  readonly value = input(0);

  /** Número máximo de estrelas */
  readonly max = input(5);

  /** Tamanho de exibição */
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  /** Modo somente leitura (apenas exibição) */
  readonly readonly = input(false);

  /** Emitido quando a avaliação muda */
  readonly valueChange = output<number>();

  protected readonly hovered = signal(0);

  protected readonly stars = computed(() => Array.from({ length: this.max() }, (_, i) => i + 1));

  protected select(star: number): void {
    if (this.readonly()) return;
    this.valueChange.emit(star);
  }
}
