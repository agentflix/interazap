import { Component, ChangeDetectionStrategy, input, output, HostListener } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';

import type { AfCommandItem } from './command-palette.model';
export * from './command-palette.model';



/**
 * Paleta de comandos pesquisável acionada por ⌘K / Ctrl+K.
 *
 * @example
 * ```html
 * <af-command-palette [items]="commands" [open]="paletteOpen()" (itemSelected)="run($event)" (closed)="close()" />
 * ```
 */
@Component({
  selector: 'af-command-palette',
  standalone: true,
  imports: [ReactiveFormsModule, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './command-palette.html',
})
export class AfCommandPaletteComponent {
  /** Indica se a paleta está aberta */
  readonly open = input(false);

  /** Comandos disponíveis */
  readonly items = input<AfCommandItem[]>([]);

  /** Emitido quando um item é selecionado */
  readonly itemSelected = output<string>();

  /** Emitido quando a paleta deve ser fechada */
  readonly closed = output<void>();

  protected readonly searchCtrl = new FormControl('', { nonNullable: true });

  protected filteredItems(): AfCommandItem[] {
    const term = this.searchCtrl.value.toLowerCase();
    if (!term) return this.items();
    return this.items().filter(
      (i) => i.label.toLowerCase().includes(term) || i.description?.toLowerCase().includes(term),
    );
  }

  protected selectItem(item: AfCommandItem): void {
    this.itemSelected.emit(item.id);
    this.closed.emit();
    this.searchCtrl.reset();
  }

  @HostListener('document:keydown', ['$event'])
  protected onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && this.open()) {
      this.closed.emit();
    }
  }
}
