import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Interface de fallback para estados de erro.
 *
 * @example
 * ```html
 * <af-error-boundary title="Algo deu errado" message="Tente novamente mais tarde." (retryClicked)="retry()" />
 * ```
 */
@Component({
  selector: 'af-error-boundary',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './error-boundary.html',
})
export class AfErrorBoundaryComponent {
  /** Título do erro */
  readonly title = input('Algo deu errado');

  /** Mensagem de erro */
  readonly message = input('Ocorreu um erro inesperado. Por favor, tente novamente.');

  /** Exibe o botão de tentar novamente */
  readonly showRetry = input(true);

  /** Emitido ao clicar em tentar novamente */
  readonly retryClicked = output<void>();
}
