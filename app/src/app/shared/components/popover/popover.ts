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

/**
 * Popover acionado por clique com conteúdo arbitrário projetado.
 *
 * @example
 * ```html
 * <af-popover>
 *   <button trigger>Clique aqui</button>
 *   <div content>
 *     <p>Conteúdo do popover</p>
 *   </div>
 * </af-popover>
 * ```
 */
@Component({
  selector: 'af-popover',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './popover.html',
})
export class AfPopoverComponent {
  /** Alinhamento horizontal */
  readonly align = input<'start' | 'center' | 'end'>('start');

  protected readonly isOpen = signal(false);

  private readonly rootRef = viewChild<ElementRef<HTMLElement>>('root');

  protected toggle(): void {
    this.isOpen.update((v) => !v);
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
