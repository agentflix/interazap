import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfErrorBoundaryComponent — Fallback UI for error states.
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
  /** Error title */
  readonly title = input('Algo deu errado');

  /** Error message */
  readonly message = input('Ocorreu um erro inesperado. Por favor, tente novamente.');

  /** Show retry button */
  readonly showRetry = input(true);

  /** Retry clicked */
  readonly retryClicked = output<void>();
}
