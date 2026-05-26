import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { AfEmptyStateComponent } from '@shared/components';

import type { AfKanbanCard } from './kanban-column.model';
export * from './kanban-column.model';



/**
 * Coluna individual do quadro kanban.
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
  /** Título da coluna */
  readonly title = input('');

  /** Cor de destaque da coluna */
  readonly color = input('#6366f1');

  /** Cards nesta coluna */
  readonly cards = input<AfKanbanCard[]>([]);

  /** Emitido quando um card é clicado */
  readonly cardClicked = output<string>();

  /** Emitido ao clicar em adicionar novo card */
  readonly addClicked = output<void>();
}
