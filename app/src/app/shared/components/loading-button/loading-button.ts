import { Component, ChangeDetectionStrategy, input, computed, output } from '@angular/core';
import { AfSpinnerComponent } from '../spinner/spinner';

/**
 * Botão que exibe um spinner durante carregamento e desativa interação automaticamente.
 *
 * @example
 * ```html
 * <af-loading-button [loading]="saving()" variant="primary" (click)="save()">
 *   Salvar
 * </af-loading-button>
 * ```
 */
@Component({
  selector: 'af-loading-button, app-loading-button',
  standalone: true,
  imports: [AfSpinnerComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './loading-button.html',
})
export class AfLoadingButtonComponent {
  /** Variante de estilo visual */
  readonly variant = input<'primary' | 'secondary' | 'danger' | 'success'>('primary');

  /** Tamanho do botão */
  readonly size = input<'sm' | 'md' | 'lg'>('sm');

  /** Indica se o botão está em estado de carregamento */
  readonly loading = input(false);

  /** Estado desabilitado */
  readonly disabled = input(false);

  /** Atributo type do botão HTML */
  readonly type = input<'button' | 'submit'>('button');

  /** Botão ocupa largura total */
  readonly block = input(false);

  /** Alias legado para largura total */
  readonly fullWidth = input(false);

  /** Texto exibido durante carregamento */
  readonly loadingText = input('Carregando...');

  /** Atributo data-test */
  readonly dataTest = input<string>();

  /** Alias legado de saída para o clique */
  readonly clicked = output<MouseEvent>();

  protected readonly spinnerSize = computed(() => {
    const map: Record<string, 'xs' | 'sm' | 'md'> = { sm: 'xs', md: 'sm', lg: 'md' };
    return map[this.size()];
  });

  protected readonly buttonClasses = computed(() => {
    const base = [
      'relative inline-flex items-center justify-center gap-2',
      'font-semibold rounded-sm',
      'transition-colors duration-150',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
      'disabled:opacity-60 disabled:cursor-not-allowed',
      'cursor-pointer select-none',
    ];

    const sizes: Record<string, string> = {
      sm: 'px-3 py-1.5 text-sm',
      md: 'px-4 py-2 text-sm',
      lg: 'px-6 py-2.5 text-base',
    };

    const variants: Record<string, string> = {
      primary:
        'bg-primary-400 text-neutral-900 hover:bg-primary-500 focus-visible:ring-primary-500/50',
      secondary:
        'bg-primary-900 text-white hover:bg-primary-700 focus-visible:ring-primary-900/50',
      danger: 'bg-danger text-white hover:bg-danger-600 focus-visible:ring-danger/50',
      success: 'bg-success text-white hover:bg-success-600 focus-visible:ring-success/50',
    };

    const blockClass = this.block() || this.fullWidth() ? 'w-full' : '';

    return [...base, sizes[this.size()], variants[this.variant()], blockClass]
      .filter(Boolean)
      .join(' ');
  });

  protected onClick(event: MouseEvent): void {
    this.clicked.emit(event);
  }
}

export const LoadingButtonComponent = AfLoadingButtonComponent;
