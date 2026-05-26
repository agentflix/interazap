import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Contêiner card versátil com slots opcionais de cabeçalho, corpo e rodapé.
 *
 * Contexto: bloco de layout fundamental em painéis, listas e formulários
 * em todo o sistema InteraZap.
 *
 * @example
 * ```html
 * <af-card>
 *   <div header><h3>Título</h3></div>
 *   <p>Conteúdo do card.</p>
 *   <div footer><af-button>Ação</af-button></div>
 * </af-card>
 * ```
 */
@Component({
  selector: 'af-card',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block', '[class.h-full]': 'fullHeight()' },
  templateUrl: './card.html',
})
export class AfCardComponent {
  /** Aplica padding padrão ao corpo do card */
  readonly padded = input(true);

  /** Estica o card para ocupar toda a altura do pai (alinhamento em grid) */
  readonly fullHeight = input(false);
}
