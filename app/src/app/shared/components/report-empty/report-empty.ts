import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { AfCardComponent } from '../card/card';
import { AfEmptyStateComponent } from '../empty-state/empty-state';

/**
 * Wrapper de estado vazio para páginas de relatório.
 *
 * Envolve af-empty-state dentro de um af-card para layouts de relatório consistentes.
 *
 * @example
 * ```html
 * <af-report-empty
 *   title="Sem dados disponíveis"
 *   description="Não há dados no período selecionado."
 * />
 * ```
 */
@Component({
  selector: 'af-report-empty',
  standalone: true,
  imports: [AfCardComponent, AfEmptyStateComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-empty.html',
})
export class AfReportEmptyComponent {
  /** Título do estado vazio */
  readonly title = input.required<string>();

  /** Descrição do estado vazio */
  readonly description = input<string | null>(null);
}
