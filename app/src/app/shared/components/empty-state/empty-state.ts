import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { NgIcon } from '@ng-icons/core';

/**
 * Placeholder de estado vazio para páginas ou seções sem dados.
 *
 * @example
 * ```html
 * <af-empty-state
 *   title="Nenhum contato ainda"
 *   description="Adicione seu primeiro contato para começar."
 * >
 *   <button af-button>+ Novo Contato</button>
 * </af-empty-state>
 * ```
 */
@Component({
  selector: 'af-empty-state',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './empty-state.html',
  imports: [NgIcon],
})
export class AfEmptyStateComponent {
  /** Título do estado vazio */
  readonly title = input.required<string>();

  /** Texto de descrição opcional */
  readonly description = input<string | null>(null);

  /** Indica se um ícone customizado é projetado via ng-content */
  readonly icon = input(false);

  /** Nome do ícone Lucide (ex.: "lucideUser"). Substitui o SVG padrão quando definido. */
  readonly iconName = input<string | null>(null);
}
