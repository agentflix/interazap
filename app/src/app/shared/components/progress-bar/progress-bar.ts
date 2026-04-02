import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfProgressBarComponent — Horizontal progress indicator.
 *
 * @example
 * ```html
 * <af-progress-bar [value]="65" variant="accent" size="md" />
 * ```
 */
@Component({
  selector: 'af-progress-bar',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './progress-bar.html',
})
export class AfProgressBarComponent {
  /** Progress value (0–100) */
  readonly value = input(0);

  /** Optional label */
  readonly label = input('');

  /** Show percentage label */
  readonly showLabel = input(true);

  /** Bar size */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  /** Color variant */
  readonly variant = input<'accent' | 'info' | 'danger' | 'warning'>('accent');

  protected readonly clampedValue = computed(() => Math.max(0, Math.min(100, this.value())));
}
