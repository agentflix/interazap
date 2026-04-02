import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfTableActionsComponent — Standard view/edit/delete action buttons for table rows.
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
  /** Show view button */
  readonly showView = input(false);

  /** Show edit button */
  readonly showEdit = input(true);

  /** Show delete button */
  readonly showDelete = input(true);

  /** Disable delete action button */
  readonly deleteDisabled = input(false);

  /** Emitted on view click */
  readonly view = output<void>();

  /** Emitted on edit click */
  readonly edit = output<void>();

  /** Emitted on delete click */
  readonly delete = output<void>();
}
