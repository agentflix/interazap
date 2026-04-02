import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfButtonGroupComponent — Layout wrapper that groups buttons
 * with consistent spacing and direction.
 *
 * @example
 * ```html
 * <af-button-group spacing="sm" direction="horizontal">
 *   <af-button variant="primary">Save</af-button>
 *   <af-button variant="ghost">Cancel</af-button>
 * </af-button-group>
 * ```
 */
@Component({
  selector: 'af-button-group',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './button-group.html',
})
export class AfButtonGroupComponent {
  /** Gap between buttons */
  readonly spacing = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  /** Layout direction */
  readonly direction = input<'horizontal' | 'vertical'>('horizontal');

  protected readonly containerClasses = computed(() => {
    const gaps: Record<string, string> = { xs: 'gap-1', sm: 'gap-2', md: 'gap-3', lg: 'gap-4' };
    const dir = this.direction() === 'vertical' ? 'flex-col' : 'flex-row flex-wrap';
    return `flex items-center ${dir} ${gaps[this.spacing()]}`;
  });
}
