import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfAspectRatioComponent — Enforces aspect ratio on child content.
 *
 * @example
 * ```html
 * <af-aspect-ratio ratio="16/9">
 *   <img src="video-thumb.jpg" class="w-full h-full object-cover" />
 * </af-aspect-ratio>
 * ```
 */
@Component({
  selector: 'af-aspect-ratio',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './aspect-ratio.html',
})
export class AfAspectRatioComponent {
  /** CSS aspect-ratio value (e.g. '16/9', '4/3', '1/1') */
  readonly ratio = input('16/9');
}
