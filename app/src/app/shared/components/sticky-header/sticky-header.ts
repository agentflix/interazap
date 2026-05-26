import {
  Component,
  ChangeDetectionStrategy,
  input,
  signal,
  HostListener,
  computed,
  ElementRef,
  inject,
} from '@angular/core';

/**
 * Container com cabeçalho fixo durante a rolagem.
 *
 * @example
 * ```html
 * <af-sticky-header>
 *   <div header>
 *     <h1>Título da Página</h1>
 *   </div>
 *   <div>Conteúdo rolável</div>
 * </af-sticky-header>
 * ```
 */
@Component({
  selector: 'af-sticky-header',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './sticky-header.html',
  host: { class: 'block' },
})
export class AfStickyHeaderComponent {
  /** Deslocamento em pixels antes de aplicar estilos de fixação */
  readonly offset = input(0);

  protected readonly isStuck = signal(false);

  @HostListener('window:scroll')
  protected onScroll(): void {
    this.isStuck.set(window.scrollY > this.offset());
  }
}
