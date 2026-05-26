import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfButtonComponent } from '../button/button';
import { AfCardComponent } from '../card/card';

/**
 * Card de estado de erro para páginas de relatório.
 *
 * Exibe uma mensagem de erro com botão de tentar novamente.
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
  /** Título do erro */
  readonly title = input('Erro ao carregar dados');

  /** Mensagem descritiva do erro */
  readonly message = input('Não foi possível carregar o relatório.');

  /** Emitido ao clicar no botão de tentar novamente */
  readonly retry = output<void>();
}
