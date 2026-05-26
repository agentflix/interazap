import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Conteúdo ou texto com rolagem horizontal animada.
 *
 * @example
 * ```html
 * <af-marquee speed="slow">
 *   <span>Novidade: InteraZap v2.0 lançado!</span>
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
  /** Predefinição de velocidade */
  readonly speed = input<'slow' | 'normal' | 'fast'>('normal');

  /** Pausa ao passar o cursor */
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
