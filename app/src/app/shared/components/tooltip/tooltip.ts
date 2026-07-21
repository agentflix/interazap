import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Tooltip simples exibido via CSS usando `group` e `hover`.
 *
 * @example
 * ```html
 * <af-tooltip text="Editar este item" position="top">
 *   <button>Editar</button>
 * </af-tooltip>
 * ```
 */
@Component({
  selector: 'af-tooltip',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'relative inline-flex group' },
  templateUrl: './tooltip.html',
})
export class AfTooltipComponent {
  /** Texto do tooltip */
  readonly text = input.required<string>();

  /** Posição relativa ao elemento acionador */
  readonly position = input<'top' | 'bottom' | 'left' | 'right'>('top');

  protected readonly tooltipClasses = computed(() => {
    const base = [
      'absolute z-50 px-2.5 py-1.5',
      'text-xs font-medium text-white',
      'bg-neutral-900 dark:bg-[#232823]',
      'rounded-md shadow-lg',
      'whitespace-nowrap pointer-events-none',
      'opacity-0 group-hover:opacity-100',
      'transition-opacity duration-150',
    ].join(' ');

    const positions: Record<string, string> = {
      top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
      bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
      left: 'right-full top-1/2 -translate-y-1/2 mr-2',
      right: 'left-full top-1/2 -translate-y-1/2 ml-2',
    };

    return `${base} ${positions[this.position()]}`;
  });
}
