import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfProgressCircleComponent — Circular SVG progress indicator.
 *
 * @example
 * ```html
 * <af-progress-circle [value]="75" size="lg" />
 * ```
 */
@Component({
  selector: 'af-progress-circle',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './progress-circle.html',
})
export class AfProgressCircleComponent {
  /** Progress value (0–100) */
  readonly value = input(0);

  /** Display size */
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  /** Color variant */
  readonly variant = input<'accent' | 'info' | 'danger' | 'warning'>('accent');

  /** Show percentage text */
  readonly showValue = input(true);

  protected readonly radius = 15.5;
  protected readonly circumference = 2 * Math.PI * 15.5;

  protected readonly clamped = computed(() => Math.max(0, Math.min(100, this.value())));
  protected readonly dashOffset = computed(
    () => this.circumference - (this.clamped() / 100) * this.circumference,
  );

  protected readonly strokeWidth = computed(() => {
    const map: Record<string, number> = { sm: 3, md: 2.5, lg: 2 };
    return map[this.size()];
  });

  protected readonly sizeClass = computed(() => {
    const map: Record<string, string> = { sm: 'size-10', md: 'size-16', lg: 'size-24' };
    return map[this.size()];
  });

  protected readonly labelClass = computed(() => {
    const map: Record<string, string> = { sm: 'text-[8px]', md: 'text-xs', lg: 'text-base' };
    return map[this.size()];
  });
}
