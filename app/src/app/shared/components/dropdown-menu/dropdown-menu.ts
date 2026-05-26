import {
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  HostListener,
  viewChild,
} from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfDropdownMenuItem } from './dropdown-menu.model';
export * from './dropdown-menu.model';



/**
 * Menu dropdown de contexto/ação acionado por conteúdo arbitrário.
 *
 * @example
 * ```html
 * <af-dropdown-menu [items]="menuItems" (itemSelected)="onAction($event)">
 *   <af-icon-button trigger label="Menu" variant="ghost" size="sm">
 *     <lucide-icon name="more-vertical" [size]="16" />
 *   </af-icon-button>
 * </af-dropdown-menu>
 * ```
 */
@Component({
  selector: 'af-dropdown-menu',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dropdown-menu.html',
})
export class AfDropdownMenuComponent {
  /** Itens do menu */
  readonly items = input<AfDropdownMenuItem[]>([]);

  /** Alinhamento do menu */
  readonly align = input<'start' | 'end'>('end');

  /** Emitido quando um item é selecionado */
  readonly itemSelected = output<string>();

  protected readonly isOpen = signal(false);

  private readonly rootRef = viewChild<ElementRef<HTMLElement>>('root');

  protected toggle(): void {
    this.isOpen.update((v) => !v);
  }

  protected select(item: AfDropdownMenuItem): void {
    if (item.disabled) return;
    this.itemSelected.emit(item.value);
    this.isOpen.set(false);
  }

  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (!this.isOpen()) return;
    const root = this.rootRef()?.nativeElement;
    if (root && event.target instanceof Node && !root.contains(event.target)) {
      this.isOpen.set(false);
    }
  }
}
