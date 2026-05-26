import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfSortableItem } from './sortable-list.model';
export * from './sortable-list.model';



/**
 * Lista reordenável com alças de arrasto.
 *
 * @example
 * ```html
 * <af-sortable-list [items]="tasks" (reordered)="onReorder($event)" />
 * ```
 */
@Component({
  selector: 'af-sortable-list',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './sortable-list.html',
})
export class AfSortableListComponent {
  /** Itens da lista */
  readonly items = input<AfSortableItem[]>([]);

  /** Emitido após reordenação com a nova ordem */
  readonly reordered = output<AfSortableItem[]>();

  private dragIndex = -1;

  protected onDragStart(index: number): void {
    this.dragIndex = index;
  }

  protected onDragOver(event: DragEvent, index: number): void {
    event.preventDefault();
  }

  protected onDrop(index: number): void {
    if (this.dragIndex === index) return;
    const copy = [...this.items()];
    const [moved] = copy.splice(this.dragIndex, 1);
    copy.splice(index, 0, moved);
    this.reordered.emit(copy);
    this.dragIndex = -1;
  }
}
