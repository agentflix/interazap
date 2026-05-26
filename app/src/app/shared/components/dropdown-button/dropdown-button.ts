import {
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  viewChild,
  HostListener,
} from '@angular/core';
import { AfButtonComponent } from '../button/button';
import { LucideAngularModule } from 'lucide-angular';

import type { AfDropdownOption } from './dropdown-button.model';
export * from './dropdown-button.model';



/**
 * Botão com menu dropdown de ações.
 *
 * @example
 * ```html
 * <af-dropdown-button
 *   label="Ações"
 *   [options]="actionOptions"
 *   (optionSelected)="onAction($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-dropdown-button',
  standalone: true,
  imports: [AfButtonComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dropdown-button.html',
})
export class AfDropdownButtonComponent {
  /** Rótulo do botão */
  readonly label = input.required<string>();

  /** Opções do dropdown */
  readonly options = input.required<AfDropdownOption[]>();

  /** Variante visual */
  readonly variant = input<'primary' | 'secondary' | 'ghost' | 'danger' | 'outline'>('secondary');

  /** Tamanho do botão */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  /** Posicionamento do dropdown */
  readonly placement = input<'bottom' | 'bottom-end' | 'top'>('bottom');

  /** Estado desabilitado */
  readonly disabled = input(false);

  /** Emitido quando uma opção é selecionada */
  readonly optionSelected = output<string>();

  protected readonly isOpen = signal(false);

  private readonly rootRef = viewChild<ElementRef<HTMLElement>>('root');

  protected toggle(): void {
    this.isOpen.update((v) => !v);
  }

  protected select(opt: AfDropdownOption): void {
    if (opt.disabled) return;
    this.optionSelected.emit(opt.value);
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
