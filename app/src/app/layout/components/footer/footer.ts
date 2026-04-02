import { Component, ChangeDetectionStrategy } from '@angular/core';

/**
 * Application footer with copyright and version info.
 *
 * @example
 * ```html
 * <af-footer />
 * ```
 */
@Component({
  selector: 'af-footer',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './footer.html',
})
export class FooterComponent {
  readonly year = new Date().getFullYear();
}
