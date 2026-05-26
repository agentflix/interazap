import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Contêiner expansível/recolhível genérico.
 *
 * @example
 * ```html
 * <af-collapsible title="Opções avançadas">
 *   <p>Conteúdo oculto...</p>
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
  /** Rótulo do gatilho para expandir/recolher */
  readonly title = input('');

  /** Inicia expandido */
  readonly open = input(false);

  protected readonly isOpen = signal(false);

  constructor() {
    queueMicrotask(() => this.isOpen.set(this.open()));
  }

  protected toggle(): void {
    this.isOpen.update((v) => !v);
  }
}
