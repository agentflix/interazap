import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Contêiner de layout que agrupa botões com espaçamento e direção consistentes.
 *
 * Contexto: utilizado em rodapés de formulários, barras de ações e
 * qualquer área que exiba múltiplos botões relacionados.
 *
 * @example
 * ```html
 * <af-button-group spacing="sm" direction="horizontal">
 *   <af-button variant="primary">Salvar</af-button>
 *   <af-button variant="ghost">Cancelar</af-button>
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
  /** Espaçamento entre botões */
  readonly spacing = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  /** Direção do layout */
  readonly direction = input<'horizontal' | 'vertical'>('horizontal');

  protected readonly containerClasses = computed(() => {
    const gaps: Record<string, string> = { xs: 'gap-1', sm: 'gap-2', md: 'gap-3', lg: 'gap-4' };
    const dir = this.direction() === 'vertical' ? 'flex-col' : 'flex-row flex-wrap';
    return `flex items-center ${dir} ${gaps[this.spacing()]}`;
  });
}
