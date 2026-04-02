import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfTimelineEntry } from './timeline.model';
export * from './timeline.model';



/**
 * AfTimelineComponent — Vertical timeline for activity logs and history.
 *
 * @example
 * ```html
 * <af-timeline [entries]="activityLog" />
 * ```
 */
@Component({
  selector: 'af-timeline',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './timeline.html',
})
export class AfTimelineComponent {
  /** Timeline entries */
  readonly entries = input<AfTimelineEntry[]>([]);
}
