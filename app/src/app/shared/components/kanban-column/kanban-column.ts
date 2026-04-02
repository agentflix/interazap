import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { AfEmptyStateComponent } from '@shared/components';

import type { AfKanbanCard } from './kanban-column.model';
export * from './kanban-column.model';



/**
 * AfKanbanColumnComponent — Single kanban board column.
 *
 * @example
 * ```html
 * <af-kanban-column title="Em progresso" [cards]="inProgressCards" color="#f59e0b"
 *   (cardClicked)="onCard($event)" (addClicked)="addCard('in-progress')" />
 * ```
 */
@Component({
  selector: 'af-kanban-column',
  standalone: true,
  imports: [LucideAngularModule, AfEmptyStateComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './kanban-column.html',
})
export class AfKanbanColumnComponent {
  /** Column title */
  readonly title = input('');

  /** Column accent color */
  readonly color = input('#6366f1');

  /** Cards in this column */
  readonly cards = input<AfKanbanCard[]>([]);

  /** Card clicked */
  readonly cardClicked = output<string>();

  /** Add new card */
  readonly addClicked = output<void>();
}
