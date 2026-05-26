import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Botões de ação padrão (ver/editar/excluir) para linhas de tabela.
 *
 * @example
 * ```html
 * <af-table-actions
 *   [showView]="true"
 *   (view)="onView(item)"
 *   (edit)="onEdit(item)"
 *   (delete)="onDelete(item)"
 * />
 * ```
 */
@Component({
  selector: 'af-table-actions',
  standalone: true,
  imports: [AfIconButtonComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './table-actions.html',
})
export class AfTableActionsComponent {
  /** Exibe botão de visualização */
  readonly showView = input(false);

  /** Exibe botão de edição */
  readonly showEdit = input(true);

  /** Exibe botão de exclusão */
  readonly showDelete = input(true);

  /** Desabilita o botão de exclusão */
  readonly deleteDisabled = input(false);

  /** Emitido ao clicar em visualizar */
  readonly view = output<void>();

  /** Emitido ao clicar em editar */
  readonly edit = output<void>();

  /** Emitido ao clicar em excluir */
  readonly delete = output<void>();
}
