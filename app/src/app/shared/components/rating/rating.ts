import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfRatingComponent — Star rating input/display.
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
  /** Current rating value */
  readonly value = input(0);

  /** Max number of stars */
  readonly max = input(5);

  /** Display size */
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  /** Read-only mode (display only) */
  readonly readonly = input(false);

  /** Emitted when rating changes */
  readonly valueChange = output<number>();

  protected readonly hovered = signal(0);

  protected readonly stars = computed(() => Array.from({ length: this.max() }, (_, i) => i + 1));

  protected select(star: number): void {
    if (this.readonly()) return;
    this.valueChange.emit(star);
  }
}
