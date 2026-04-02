import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Colored status indicator badge with dot.
 *
 * @example
 * ```html
 * <af-status-badge status="online">Online</af-status-badge>
 * <af-status-badge status="offline">Offline</af-status-badge>
 * <af-status-badge status="error">Error</af-status-badge>
 * ```
 */
@Component({
  selector: 'af-status-badge',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './status-badge.html',
})
export class AfStatusBadgeComponent {
  /** Status type determines color */
  readonly status = input<'online' | 'offline' | 'warning' | 'error' | 'idle'>('online');

  /** Show a pulse animation on the dot */
  readonly pulse = input(false);

  protected readonly badgeClasses = computed(() => {
    return 'inline-flex items-center gap-2 text-xs font-medium text-neutral-700 dark:text-neutral-300';
  });

  protected readonly dotClasses = computed(() => {
    const base = 'relative size-2 rounded-full';
    const colors: Record<string, string> = {
      online: 'bg-emerald-500',
      offline: 'bg-neutral-400',
      warning: 'bg-amber-500',
      error: 'bg-red-500',
      idle: 'bg-sky-500',
    };
    return `${base} ${colors[this.status()]}`;
  });

  protected readonly pulseClasses = computed(() => {
    const colors: Record<string, string> = {
      online: 'bg-emerald-400',
      offline: 'bg-neutral-300',
      warning: 'bg-amber-400',
      error: 'bg-red-400',
      idle: 'bg-sky-400',
    };
    return `absolute inset-0 rounded-full animate-ping ${colors[this.status()]}`;
  });
}
