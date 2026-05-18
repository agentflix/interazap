import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { AfCardComponent } from '../card/card';
import { AfEmptyStateComponent } from '../empty-state/empty-state';

/**
 * AfReportEmptyComponent — Empty state wrapper for report pages.
 *
 * Wraps af-empty-state inside an af-card for consistent report layouts.
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
  /** Empty state title */
  readonly title = input.required<string>();

  /** Empty state description */
  readonly description = input<string | null>(null);
}
