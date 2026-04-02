import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfMarqueeComponent — Horizontally scrolling text/content.
 *
 * @example
 * ```html
 * <af-marquee speed="slow">
 *   <span>Breaking news: AgentFlix v2.0 launched!</span>
 * </af-marquee>
 * ```
 */
@Component({
  selector: 'af-marquee',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './marquee.html',
  styleUrl: './marquee.scss',
})
export class AfMarqueeComponent {
  /** Speed preset */
  readonly speed = input<'slow' | 'normal' | 'fast'>('normal');

  /** Pause on hover */
  readonly pauseOnHover = input(true);

  protected readonly containerClasses = computed(() => {
    const pause = this.pauseOnHover() ? 'hover:[&_.animate-marquee]:pause' : '';
    return pause;
  });

  protected readonly duration = computed(() => {
    const map: Record<string, string> = { slow: '30s', normal: '20s', fast: '10s' };
    return map[this.speed()];
  });
}
