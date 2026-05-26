import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Barra de navegação horizontal para navegação secundária.
 *
 * @example
 * ```html
 * <af-navbar [title]="'Configurações'" [showBack]="true" (back)="goBack()">
 *   <ng-content /> <!-- ações no lado direito -->
 * </af-navbar>
 * ```
 */
@Component({
  selector: 'af-navbar',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './navbar.html',
})
export class AfNavbarComponent {
  /** Título da barra de navegação */
  readonly title = input('');

  /** Exibe a seta de voltar */
  readonly showBack = input(false);

  /** Emitido ao clicar em voltar */
  readonly back = output<void>();
}
