import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Placeholder animado de carregamento de conteúdo.
 *
 * @example
 * ```html
 * <af-skeleton variant="text" width="200px" />
 * <af-skeleton variant="circular" width="40px" height="40px" />
 * <af-skeleton variant="rectangular" width="100%" height="120px" />
 * ```
 */
@Component({
  selector: 'af-skeleton',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './skeleton.html',
})
export class AfSkeletonComponent {
  /** Variante de forma do skeleton */
  readonly variant = input<'text' | 'circular' | 'rectangular'>('text');

  /** Largura CSS */
  readonly width = input('100%');

  /** Altura CSS (detectada automaticamente para texto) */
  readonly height = input<string | null>(null);

  protected readonly computedHeight = computed(() => {
    if (this.height()) return this.height();
    const defaults: Record<string, string> = {
      text: '1rem',
      circular: this.width(),
      rectangular: '4rem',
    };
    return defaults[this.variant()];
  });

  protected readonly skeletonClasses = computed(() => {
    const base = 'animate-pulse bg-neutral-200 dark:bg-neutral-700';
    const shapes: Record<string, string> = {
      text: 'rounded',
      circular: 'rounded-full',
      rectangular: 'rounded-lg',
    };
    return `${base} ${shapes[this.variant()]}`;
  });
}
