import { Component, ChangeDetectionStrategy, input, computed, output } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import * as lucideIcons from '@ng-icons/lucide';

/**
 * Botão somente ícone com rótulo acessível.
 *
 * Renderiza o ícone automaticamente a partir do input `icon` (nome do ícone Lucide).
 * Usa conteúdo projetado como fallback quando `icon` não é informado.
 *
 * @example
 * ```html
 * <af-icon-button icon="lucideSend" variant="ghost" size="sm" label="Enviar" />
 * ```
 */
@Component({
  selector: 'af-icon-button, app-icon-button',
  standalone: true,
  imports: [NgIcon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [provideIcons(lucideIcons)],
  templateUrl: './icon-button.html',
})
export class AfIconButtonComponent {
  /** Rótulo acessível para leitores de tela */
  readonly label = input('');

  /** Alias legado para aria-label */
  readonly ariaLabel = input<string>();

  /** Nome do ícone Lucide (ex.: 'lucideSend') */
  readonly icon = input<string>();

  /** Variante de estilo visual */
  readonly variant = input<'default' | 'ghost' | 'outline' | 'danger' | 'success' | 'primary'>(
    'default',
  );

  /** Tamanho do botão */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('sm');

  /** Variante do raio da borda */
  readonly rounded = input<'md' | 'full'>('md');

  /** Estado desabilitado */
  readonly disabled = input(false);

  /** Alias legado de saída para o clique */
  readonly clicked = output<MouseEvent>();

  protected readonly resolvedLabel = computed(() => {
    const aria = this.ariaLabel();
    if (aria !== undefined && aria !== null && aria.length > 0) return aria;
    return this.label();
  });

  /** Tamanho do ícone baseado no tamanho do botão */
  protected readonly iconSize = computed(() => {
    const map: Record<string, string> = { xs: '14', sm: '16', md: '18', lg: '20' };
    return map[this.size()];
  });

  protected readonly buttonClasses = computed(() => {
    const roundedMap: Record<string, string> = { md: 'rounded-sm', full: 'rounded-full' };

    const base = [
      'inline-flex items-center justify-center',
      roundedMap[this.rounded()],
      'transition-colors duration-150',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'cursor-pointer',
    ];

    const sizes: Record<string, string> = {
      xs: 'size-6',
      sm: 'size-8',
      md: 'size-9',
      lg: 'size-11',
    };

    const variants: Record<string, string> = {
      default: [
        'text-neutral-600 dark:text-neutral-400',
        'hover:bg-neutral-100 dark:hover:bg-[#191d1a]',
        'hover:text-neutral-900 dark:hover:text-neutral-100',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
      ghost: [
        'text-neutral-500 dark:text-neutral-400',
        'hover:bg-neutral-100 dark:hover:bg-[#191d1a]',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
      outline: [
        'border border-hairline-strong dark:border-white/[0.14]',
        'text-neutral-600 dark:text-neutral-400',
        'hover:bg-surface-50 dark:hover:bg-[#191d1a]',
        'focus-visible:ring-neutral-400/50',
      ].join(' '),
      danger: [
        'text-danger-600 dark:text-danger-400',
        'hover:bg-danger-50 dark:hover:bg-danger-950',
        'focus-visible:ring-danger-600/50',
      ].join(' '),
      success: [
        'text-success',
        'hover:bg-success/10',
        'focus-visible:ring-success/50',
      ].join(' '),
      primary: [
        'text-primary-600 dark:text-primary-400',
        'hover:bg-primary-50 dark:hover:bg-primary-950',
        'focus-visible:ring-primary-500/50',
      ].join(' '),
    };

    return [...base, sizes[this.size()], variants[this.variant()]].join(' ');
  });

  protected onClick(event: MouseEvent): void {
    this.clicked.emit(event);
  }
}

export const IconButtonComponent = AfIconButtonComponent;
