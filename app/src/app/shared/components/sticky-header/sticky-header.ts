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
 * AfStickyHeaderComponent — Container with a header that sticks on scroll.
 *
 * @example
 * ```html
 * <af-sticky-header>
 *   <div header>
 *     <h1>Page Title</h1>
 *   </div>
 *   <div>Scrollable content</div>
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
  /** Offset in pixels before applying stuck styles */
  readonly offset = input(0);

  protected readonly isStuck = signal(false);

  @HostListener('window:scroll')
  protected onScroll(): void {
    this.isStuck.set(window.scrollY > this.offset());
  }
}
