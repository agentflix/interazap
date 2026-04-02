import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfCollapsibleComponent — Generic expandable/collapsible container.
 *
 * @example
 * ```html
 * <af-collapsible title="Opções avançadas">
 *   <p>Hidden content...</p>
 * </af-collapsible>
 * ```
 */
@Component({
  selector: 'af-collapsible',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './collapsible.html',
})
export class AfCollapsibleComponent {
  /** Collapsible trigger label */
  readonly title = input('');

  /** Start expanded */
  readonly open = input(false);

  protected readonly isOpen = signal(false);

  constructor() {
    queueMicrotask(() => this.isOpen.set(this.open()));
  }

  protected toggle(): void {
    this.isOpen.update((v) => !v);
  }
}
