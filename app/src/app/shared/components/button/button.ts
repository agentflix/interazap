import { Component, ChangeDetectionStrategy, input, computed, output } from '@angular/core';

/**
 * Botão principal do UI Kit do InteraZap com suporte a variantes, tamanhos e estados.
 *
 * Contexto: componente base de ação em formulários, modais e barras de ferramentas
 * em todo o sistema.
 *
 * @example
 * ```html
 * <af-button variant="primary" size="md">Salvar</af-button>
 * <af-button variant="ghost" size="sm">Cancelar</af-button>
 * <af-button variant="danger" [disabled]="true">Excluir</af-button>
 * ```
 */
@Component({
  selector: 'af-button, app-button',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '[class]': 'hostClasses()',
    '[attr.disabled]': 'disabled() || null',
  },
  templateUrl: './button.html',
})
export class AfButtonComponent {
  /** Variante visual do botão */
  readonly variant = input<
    'primary' | 'secondary' | 'ghost' | 'danger' | 'outline' | 'success' | 'default'
  >('primary');

  /** Tamanho do botão */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('sm');

  /** Desabilita o botão */
  readonly disabled = input(false);

  /** Atributo type do elemento button HTML */
  readonly type = input<'button' | 'submit' | 'reset'>('button');

  /** Ocupa toda a largura disponível */
  readonly block = input(false);

  /** Alias legado para largura total */
  readonly fullWidth = input(false);

  /** Ícone legado para compatibilidade com templates antigos */
  readonly icon = input<string>();

  /** Atributo data-test para testes E2E */
  readonly dataTest = input<string>();

  /** Rótulo acessível para leitores de tela */
  readonly ariaLabel = input<string>('');

  /** Alias legado de saída para clique */
  readonly clicked = output<MouseEvent>();

  protected readonly hostClasses = computed(() => 'inline-flex');

  protected readonly buttonClasses = computed(() => {
    const base = [
      'inline-flex items-center justify-center gap-2 whitespace-nowrap',
      'font-semibold rounded-sm',
      'transition-colors duration-150',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
      'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none',
      'cursor-pointer select-none',
    ];

    const sizes: Record<string, string> = {
      xs: 'h-7 px-2.5 text-xs',
      sm: 'h-8 px-3 text-sm',
      md: 'h-10 px-4 text-sm',
      lg: 'h-11 px-6 text-base',
    };

    const variants: Record<string, string> = {
      primary: [
        'bg-primary-400 text-neutral-900',
        'hover:bg-primary-500',
        'active:bg-primary-600',
        'focus-visible:ring-primary-500/50',
      ].join(' '),
      secondary: [
        'bg-primary-900 text-white',
        'hover:bg-primary-700',
        'active:bg-primary-950',
        'focus-visible:ring-primary-900/50',
      ].join(' '),
      ghost: [
        'bg-transparent text-neutral-700 dark:text-neutral-300',
        'hover:bg-neutral-100 dark:hover:bg-neutral-800',
        'active:bg-neutral-200 dark:active:bg-neutral-700',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
      danger: [
        'bg-danger text-white',
        'hover:bg-danger-600',
        'active:bg-danger-700',
        'focus-visible:ring-danger/50',
      ].join(' '),
      outline: [
        'border border-hairline-strong dark:border-neutral-600',
        'bg-transparent text-neutral-700 dark:text-neutral-300',
        'hover:bg-surface-50 dark:hover:bg-neutral-800',
        'active:bg-neutral-100 dark:active:bg-neutral-700',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
      success: [
        'bg-success text-white',
        'hover:bg-success-600',
        'active:bg-success-700',
        'focus-visible:ring-success/50',
      ].join(' '),
      default: [
        'bg-transparent text-neutral-700 dark:text-neutral-300',
        'hover:bg-neutral-100 dark:hover:bg-neutral-800',
        'active:bg-neutral-200 dark:active:bg-neutral-700',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
    };

    const blockClass = this.block() || this.fullWidth() ? 'w-full' : '';

    return [...base, sizes[this.size()], variants[this.variant()], blockClass].join(' ');
  });

  protected onClick(event: MouseEvent): void {
    this.clicked.emit(event);
  }
}

export const ButtonComponent = AfButtonComponent;
