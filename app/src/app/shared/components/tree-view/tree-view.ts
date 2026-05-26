import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfTreeNode } from './tree-view.model';
export * from './tree-view.model';



/**
 * Visualização em árvore recursiva com expansão/recolhimento.
 *
 * @example
 * ```html
 * <af-tree-view [nodes]="fileTree" (nodeSelected)="onSelect($event)" />
 * ```
 */
@Component({
  selector: 'af-tree-view',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tree-view.html',
})
export class AfTreeViewComponent {
  /** Nós da árvore */
  readonly nodes = input<AfTreeNode[]>([]);

  /** Nível de aninhamento (interno) */
  readonly level = input(0);

  /** Emitido quando um nó folha é selecionado */
  readonly nodeSelected = output<string>();

  private readonly expandedIds = signal(new Set<string>());

  protected isExpanded(id: string): boolean {
    return this.expandedIds().has(id);
  }

  protected toggleOrSelect(node: AfTreeNode): void {
    if (node.children && node.children.length > 0) {
      this.expandedIds.update((set) => {
        const copy = new Set(set);
        if (copy.has(node.id)) copy.delete(node.id);
        else copy.add(node.id);
        return copy;
      });
    } else {
      this.nodeSelected.emit(node.id);
    }
  }
}
