import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Layout flexbox em pilha (vertical ou horizontal).
 *
 * @example
 * ```html
 * <af-stack direction="row" gap="md" align="center">
 *   <af-button>Um</af-button>
 *   <af-button>Dois</af-button>
 * </af-stack>
 * ```
 */
@Component({
  selector: 'af-stack',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './stack.html',
})
export class AfStackComponent {
  /** Direção flex */
  readonly direction = input<'row' | 'column'>('column');

  /** Tamanho do espaçamento */
  readonly gap = input<'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl'>('md');

  /** Alinhamento dos itens */
  readonly align = input<'start' | 'center' | 'end' | 'stretch'>('stretch');

  /** Justificação do conteúdo */
  readonly justify = input<'start' | 'center' | 'end' | 'between' | 'around'>('start');

  /** Quebra de linha */
  readonly wrap = input(false);

  protected readonly stackClasses = computed(() => {
    const dir = this.direction() === 'row' ? 'flex-row' : 'flex-col';
    const gapMap: Record<string, string> = {
      none: 'gap-0',
      xs: 'gap-1',
      sm: 'gap-2',
      md: 'gap-4',
      lg: 'gap-6',
      xl: 'gap-8',
    };
    const alignMap: Record<string, string> = {
      start: 'items-start',
      center: 'items-center',
      end: 'items-end',
      stretch: 'items-stretch',
    };
    const justifyMap: Record<string, string> = {
      start: 'justify-start',
      center: 'justify-center',
      end: 'justify-end',
      between: 'justify-between',
      around: 'justify-around',
    };
    const w = this.wrap() ? 'flex-wrap' : '';
    return `flex ${dir} ${gapMap[this.gap()]} ${alignMap[this.align()]} ${justifyMap[this.justify()]} ${w}`.trim();
  });
}
