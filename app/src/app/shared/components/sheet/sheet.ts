import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';

/**
 * Sheet (painel inferior) para ações mobile-friendly.
 *
 * @example
 * ```html
 * <af-sheet [open]="sheetOpen()" title="Opções" (closed)="sheetOpen.set(false)">
 *   <p>Conteúdo aqui</p>
 * </af-sheet>
 * ```
 */
@Component({
  selector: 'af-sheet',
  standalone: true,
  imports: [AfIconButtonComponent, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './sheet.html',
})
export class AfSheetComponent {
  /** Indica se o sheet está aberto */
  readonly open = input(false);

  /** Título do sheet */
  readonly title = input('');

  /** Evento de fechamento */
  readonly closed = output<void>();
}
