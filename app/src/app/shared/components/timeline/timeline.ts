import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfTimelineEntry } from './timeline.model';
export * from './timeline.model';



/**
 * Linha do tempo vertical para registros de atividade e histórico.
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
  /** Entradas da linha do tempo */
  readonly entries = input<AfTimelineEntry[]>([]);
}
