import { Component, ChangeDetectionStrategy, HostListener, input, output } from '@angular/core';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';

/**
 * Painel deslizante lateral que emerge da borda da tela.
 *
 * @example
 * ```html
 * <af-drawer [open]="drawerOpen()" (closed)="drawerOpen.set(false)" title="Filtros">
 *   <p>Conteúdo do painel</p>
 * </af-drawer>
 * ```
 */
@Component({
  selector: 'af-drawer',
  standalone: true,
  imports: [AfIconButtonComponent, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './drawer.html',
})
export class AfDrawerComponent {
  /** Indica se o painel está aberto */
  readonly open = input(false);

  /** Título do painel */
  readonly title = input('');

  /** Lado de onde o painel emerge */
  readonly side = input<'left' | 'right'>('right');

  /** Largura do painel */
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  /** Emitido quando o painel deve ser fechado */
  readonly closed = output<void>();

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.open()) {
      this.closed.emit();
    }
  }
}
