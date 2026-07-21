import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Rótulo/tag pequeno para status e categorização.
 *
 * Usa forma arredondada com variantes de cor semânticas.
 *
 * @example
 * ```html
 * <af-pill variant="success">Ativo</af-pill>
 * <af-pill variant="warning" dot>Pendente</af-pill>
 * <af-pill variant="danger">Bloqueado</af-pill>
 * ```
 */
@Component({
  selector: 'af-pill',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './pill.html',
})
export class AfPillComponent {
  /** Variante de cor */
  readonly variant = input<'default' | 'success' | 'warning' | 'danger' | 'info'>('default');

  /** Exibe um indicador circular antes do texto */
  readonly dot = input(false);

  protected readonly pillClasses = computed(() => {
    const base =
      'inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full whitespace-nowrap transition-colors duration-150';

    const variants: Record<string, string> = {
      default: 'bg-neutral-100 text-neutral-700 dark:bg-[#191d1a] dark:text-neutral-300',
      success: 'bg-primary-50 text-primary-700 dark:bg-primary-900 dark:text-primary-300',
      warning: 'bg-warning-50 text-warning-600 dark:bg-[#191d1a] dark:text-warning-500',
      danger: 'bg-danger-50 text-danger-600 dark:bg-[#191d1a] dark:text-danger-500',
      info: 'bg-info-50 text-info-500 dark:bg-[#191d1a] dark:text-info-500',
    };

    return `${base} ${variants[this.variant()]}`;
  });

  protected readonly dotClasses = computed(() => {
    const base = 'size-1.5 rounded-full';

    const variants: Record<string, string> = {
      default: 'bg-neutral-500',
      success: 'bg-primary-500',
      warning: 'bg-warning-500',
      danger: 'bg-danger-500',
      info: 'bg-info-500',
    };

    return `${base} ${variants[this.variant()]}`;
  });
}

export const PillComponent = AfPillComponent;
