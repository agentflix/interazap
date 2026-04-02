import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfResizablePanelComponent — Horizontally resizable panel with drag handle.
 *
 * @example
 * ```html
 * <af-resizable-panel [initialWidth]="300" [minWidth]="200" [maxWidth]="500">
 *   <p>Sidebar content</p>
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
  /** Initial width in pixels */
  readonly initialWidth = input(280);

  /** Minimum width */
  readonly minWidth = input(160);

  /** Maximum width */
  readonly maxWidth = input(600);

  /** Width changed */
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
