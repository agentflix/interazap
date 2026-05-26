import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Título de página com subtítulo e área de breadcrumb opcionais.
 *
 * @example
 * ```html
 * <af-page-title title="Contatos" subtitle="Gerencie seus contatos CRM">
 *   <button>+ Novo Contato</button>
 * </af-page-title>
 * ```
 */
@Component({
  selector: 'af-page-title, app-page-title',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './page-title.html',
})
export class AfPageTitleComponent {
  /** Texto do título da página */
  readonly title = input.required<string>();

  /** Descrição opcional abaixo do título */
  readonly subtitle = input<string | null>(null);

  /** Peso visual do título */
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  protected readonly titleClasses = computed(() => {
    const base = 'font-bold tracking-tight text-neutral-900 dark:text-neutral-50';
    const sizes: Record<string, string> = {
      sm: 'text-lg',
      md: 'text-xl',
      lg: 'text-2xl',
    };
    return `${base} ${sizes[this.size()]}`;
  });
}

export const PageTitle = AfPageTitleComponent;
