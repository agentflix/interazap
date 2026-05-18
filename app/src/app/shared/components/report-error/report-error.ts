import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfButtonComponent } from '../button/button';
import { AfCardComponent } from '../card/card';

/**
 * AfReportErrorComponent — Error state card for report pages.
 *
 * Displays an error message with a retry button.
 *
 * @example
 * ```html
 * <af-report-error
 *   title="Erro ao carregar dados"
 *   message="Não foi possível carregar o relatório."
 *   (retry)="onRetry()"
 * />
 * ```
 */
@Component({
  selector: 'af-report-error',
  standalone: true,
  imports: [AfButtonComponent, AfCardComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-error.html',
})
export class AfReportErrorComponent {
  /** Error title */
  readonly title = input('Erro ao carregar dados');

  /** Error description message */
  readonly message = input('Não foi possível carregar o relatório.');

  /** Emitted when user clicks the retry button */
  readonly retry = output<void>();
}
