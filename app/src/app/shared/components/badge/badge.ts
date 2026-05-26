import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Rótulo pequeno para categorização e exibição de status.
 *
 * Contexto: utilizado em tabelas, cards e listas para indicar
 * estado, tipo ou prioridade de um item.
 *
 * @example
 * ```html
 * <af-badge variant="success">Ativo</af-badge>
 * <af-badge variant="warning" size="sm">Pendente</af-badge>
 * <af-badge variant="info" dot>Novo</af-badge>
 * ```
 */
@Component({
  selector: 'af-badge',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './badge.html',
})
export class AfBadgeComponent {
  /** Variante de cor */
  readonly variant = input<'default' | 'success' | 'warning' | 'danger' | 'info'>('default');

  /** Tamanho do badge */
  readonly size = input<'sm' | 'md'>('md');

  /** Exibe indicador circular antes do texto */
  readonly dot = input(false);

  protected readonly badgeClasses = computed(() => {
    const base =
      'inline-flex items-center gap-1.5 font-medium rounded-full whitespace-nowrap transition-colors duration-150';

    const sizes: Record<string, string> = {
      sm: 'px-2 py-0.5 text-xs',
      md: 'px-2.5 py-1 text-xs',
    };

    const variants: Record<string, string> = {
      default: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
      success: 'bg-primary-50 text-primary-700 dark:bg-primary-900 dark:text-primary-300',
      warning: 'bg-warning-50 text-warning-600 dark:bg-neutral-800 dark:text-warning-500',
      danger: 'bg-danger-50 text-danger-600 dark:bg-neutral-800 dark:text-danger-500',
      info: 'bg-info-50 text-info-500 dark:bg-neutral-800 dark:text-info-500',
    };

    return `${base} ${sizes[this.size()]} ${variants[this.variant()]}`;
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
