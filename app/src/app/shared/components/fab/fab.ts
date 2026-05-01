import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfFabComponent — Floating Action Button positioned fixed on screen.
 *
 * @example
 * ```html
 * <af-fab icon="plus" position="bottom-right" (clicked)="onCreate()" />
 * ```
 */
@Component({
  selector: 'af-fab',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './fab.html',
})
export class AfFabComponent {
  /** Lucide icon name */
  readonly icon = input.required<string>();

  /** Accessible label */
  readonly ariaLabel = input('Action');

  /** Visual variant */
  readonly variant = input<'primary' | 'secondary' | 'danger'>('primary');

  /** Button size */
  readonly size = input<'sm' | 'md' | 'lg'>('sm');

  /** Screen position */
  readonly position = input<'bottom-right' | 'bottom-left' | 'bottom-center'>('bottom-right');

  /** Disabled state */
  readonly disabled = input(false);

  /** Click event */
  readonly clicked = output<void>();

  protected readonly iconSize = computed(() => {
    const map: Record<string, number> = { sm: 18, md: 22, lg: 26 };
    return map[this.size()];
  });

  protected readonly buttonClasses = computed(() => {
    const sizes: Record<string, string> = { sm: 'size-10', md: 'size-12', lg: 'size-14' };
    const variants: Record<string, string> = {
      primary:
        'bg-accent-500 text-white hover:bg-accent-600 active:bg-accent-700 focus-visible:ring-accent-500/50',
      secondary:
        'bg-primary-900 text-white hover:bg-primary-700 active:bg-primary-950 focus-visible:ring-primary-900/50',
      danger:
        'bg-red-600 text-white hover:bg-red-700 active:bg-red-800 focus-visible:ring-red-600/50',
    };
    const positions: Record<string, string> = {
      'bottom-right': 'fixed bottom-6 right-6 mb-safe mr-safe',
      'bottom-left': 'fixed bottom-6 left-6 mb-safe ml-safe',
      'bottom-center': 'fixed bottom-6 left-1/2 -translate-x-1/2 mb-safe',
    };
    return [
      'inline-flex items-center justify-center rounded-full shadow-lg',
      'transition-all duration-150 cursor-pointer z-50',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      sizes[this.size()],
      variants[this.variant()],
      positions[this.position()],
    ].join(' ');
  });
}
