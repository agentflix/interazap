import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Painel horizontal redimensionável com alça de arrasto.
 *
 * @example
 * ```html
 * <af-resizable-panel [initialWidth]="300" [minWidth]="200" [maxWidth]="500">
 *   <p>Conteúdo da sidebar</p>
 * </af-resizable-panel>
 * ```
 */
@Component({
  selector: 'af-resizable-panel',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './resizable-panel.html',
  host: { class: 'block h-full' },
})
export class AfResizablePanelComponent {
  /** Largura inicial em pixels */
  readonly initialWidth = input(280);

  /** Largura mínima */
  readonly minWidth = input(160);

  /** Largura máxima */
  readonly maxWidth = input(600);

  /** Emitido quando a largura é alterada */
  readonly widthChanged = output<number>();

  protected readonly currentWidth = signal(0);

  private startX = 0;
  private startW = 0;

  constructor() {
    // Initialize in next tick
    queueMicrotask(() => this.currentWidth.set(this.initialWidth()));
  }

  protected startResize(event: MouseEvent): void {
    event.preventDefault();
    this.startX = event.clientX;
    this.startW = this.currentWidth();

    const onMove = (e: MouseEvent) => {
      const delta = e.clientX - this.startX;
      const newW = Math.min(this.maxWidth(), Math.max(this.minWidth(), this.startW + delta));
      this.currentWidth.set(newW);
    };

    const onUp = () => {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      this.widthChanged.emit(this.currentWidth());
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }
}
