import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Painel retrátil com cabeçalho clicável e conteúdo expansível.
 *
 * Contexto: utilizado em seções de FAQ, configurações avançadas e
 * qualquer área que exija colapso/expansão de conteúdo.
 *
 * @example
 * ```html
 * <af-accordion-item title="FAQ #1" [open]="true">
 *   <p>Conteúdo da resposta aqui.</p>
 * </af-accordion-item>
 * ```
 */
@Component({
  selector: 'af-accordion-item',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './accordion.html',
})
export class AfAccordionItemComponent {
  /** Título exibido no cabeçalho do painel */
  readonly title = input.required<string>();

  /** Estado inicial — aberto (`true`) ou fechado (`false`) */
  readonly open = input(false);

  protected readonly isOpen = signal(false);

  constructor() {
    // defer reading input to init
    queueMicrotask(() => this.isOpen.set(this.open()));
  }

  protected toggle(): void {
    this.isOpen.update((v) => !v);
  }
}
